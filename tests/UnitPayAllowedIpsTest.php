<?php

namespace Tests;

use UnitPay;
use UnitpayIpAllowlist;
use UnitpayIpException;
use PHPUnit\Framework\TestCase;

/**
 * Динамический белый список IP вебхуков: refreshAllowedIps() загружает опубликованный
 * фид (https://<domain>/ips/ips_webhooks.json), addAllowedIps() добавляет IP мерчанта
 * поверх, и каждый путь fail-safe (никогда не опустошает список, не бросает исключений).
 */
final class UnitPayAllowedIpsTest extends TestCase
{
    private const SECRET = 'secret';
    /** Один из встроенных адресов по умолчанию. */
    private const DEFAULT_IP = '31.186.100.49';

    /** Строит корректный подписанный вебхук 'pay'. */
    private function validRequest(): array
    {
        $params = [
            'account'   => '42',
            'orderSum'  => '100.00',
            'unitpayId' => '999',
        ];
        $params['signature'] = (new UnitPay('unitpay.ru', self::SECRET))->getSignature($params, 'pay');

        return ['method' => 'pay', 'params' => $params];
    }

    /** Обработчик, транспорт которого возвращает фиксированное тело для любого URL. */
    private function handler(string $feedBody, string $ip): UnitPay
    {
        return $this->handlerWithTransport(static function () use ($feedBody) {
            return $feedBody;
        }, $ip);
    }

    /** Обработчик с заданным транспортом (для эмуляции сбоев / перехвата URL). */
    private function handlerWithTransport(callable $transport, string $ip): UnitPay
    {
        return new UnitPay('unitpay.ru', self::SECRET, $transport, $this->validRequest(), $ip);
    }

    private function feed(array $ips): string
    {
        return json_encode(['webhooks' => $ips]);
    }

    // --- семантика замены ----------------------------------------------------

    public function testFetchedIpNotInDefaultBecomesAllowed(): void
    {
        $ip = '203.0.113.7'; // TEST-NET-3, нет в списке по умолчанию
        $unitPay = $this->handler($this->feed([$ip]), $ip);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testDefaultIpDroppedByFetchIsRejected(): void
    {
        // Фид больше не содержит встроенный адрес → он должен перестать быть доверенным.
        $unitPay = $this->handler($this->feed(['203.0.113.7']), self::DEFAULT_IP);
        $unitPay->refreshAllowedIps();

        $this->expectException(UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    // --- fail-safe (безопасный откат) ----------------------------------------

    public function testTransportFailureKeepsBuiltinList(): void
    {
        $unitPay = $this->handlerWithTransport(static function () {
            return false; // сбой транспорта
        }, self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMalformedJsonKeepsBuiltinList(): void
    {
        $unitPay = $this->handler('this is not json', self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMissingWebhooksKeyKeepsBuiltinList(): void
    {
        $unitPay = $this->handler('{"foo":123}', self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testEmptyFeedKeepsBuiltinList(): void
    {
        $unitPay = $this->handler($this->feed([]), self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testAllInvalidEntriesKeepBuiltinList(): void
    {
        $unitPay = $this->handler($this->feed(['garbage', '999.999.999.999']), self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
        // запасной список сохранил адреса по умолчанию, мусор не сохранён
        $this->assertContains(self::DEFAULT_IP, $unitPay->getAllowedIps());
        $this->assertNotContains('garbage', $unitPay->getAllowedIps());
    }

    // --- добавления мерчанта поверх ------------------------------------------

    public function testCustomIpSurvivesRefresh(): void
    {
        $customIp = '198.51.100.5'; // TEST-NET-2, собственный релей мерчанта
        $unitPay = $this->handler($this->feed(['203.0.113.7']), $customIp);

        $unitPay->addAllowedIps([$customIp])->refreshAllowedIps();

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- URL фида ------------------------------------------------------------

    public function testRefreshFetchesTheCanonicalFeedUrl(): void
    {
        $captured = null;
        $unitPay = $this->handlerWithTransport(static function ($url) use (&$captured) {
            $captured = $url;
            return '{"webhooks":["203.0.113.7"]}';
        }, self::DEFAULT_IP);

        $unitPay->refreshAllowedIps();

        $this->assertSame('https://unitpay.ru/ips/ips_webhooks.json', $captured);
    }

    // --- CIDR из фида --------------------------------------------------------

    public function testCidrRangeFromFeedIsHonoured(): void
    {
        $unitPay = $this->handler($this->feed(['203.0.113.0/24']), '203.0.113.55');

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    // --- фильтрация мусора ---------------------------------------------------

    public function testValidEntriesAppliedAndJunkDropped(): void
    {
        $unitPay = $this->handler($this->feed(['203.0.113.7', 'garbage']), '203.0.113.7');
        $unitPay->refreshAllowedIps();

        $this->assertSame(['203.0.113.7'], $unitPay->getAllowedIps());
        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- getAllowedIps() -----------------------------------------------------

    public function testGetAllowedIpsReturnsDedupedUnion(): void
    {
        $unitPay = new UnitPay('unitpay.ru', self::SECRET);
        $unitPay->setAllowedIps(['1.1.1.1'])->addAllowedIps(['1.1.1.1', '2.2.2.2']);

        $this->assertSame(['1.1.1.1', '2.2.2.2'], $unitPay->getAllowedIps());
    }

    public function testGetAllowedIpsDefaultsToBuiltinList(): void
    {
        $unitPay = new UnitPay('unitpay.ru', self::SECRET);

        $this->assertSame(['31.186.100.49', '51.250.20.9'], $unitPay->getAllowedIps());
    }

    // --- инвалидация кэша матчера --------------------------------------------

    public function testAddAllowedIpsInvalidatesTheMatcherCache(): void
    {
        $customIp = '198.51.100.5';
        $unitPay = $this->handler($this->feed([self::DEFAULT_IP]), $customIp);

        // Первая проверка строит и кэширует матчер без кастомного IP → отклонение.
        try {
            $unitPay->checkHandlerRequest();
            $this->fail('expected the custom IP to be rejected before it is added');
        } catch (UnitpayIpException $e) {
            // ожидаемо
        }

        // Добавление IP должно инвалидировать кэш матчера, чтобы следующая проверка его увидела.
        $unitPay->addAllowedIps([$customIp]);
        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- UnitpayIpAllowlist::isValidEntry() ----------------------------------

    /**
     * @dataProvider validEntries
     */
    public function testIsValidEntryAcceptsWellFormedEntries(string $entry): void
    {
        $this->assertTrue(UnitpayIpAllowlist::isValidEntry($entry));
    }

    public function validEntries(): array
    {
        return [
            'ipv4'      => ['31.186.100.49'],
            'ipv6'      => ['2001:db8::1'],
            'ipv4 cidr' => ['203.0.113.0/24'],
            'ipv6 cidr' => ['2001:db8::/32'],
        ];
    }

    /**
     * @dataProvider invalidEntries
     */
    public function testIsValidEntryRejectsMalformedEntries(string $entry): void
    {
        $this->assertFalse(UnitpayIpAllowlist::isValidEntry($entry));
    }

    public function invalidEntries(): array
    {
        return [
            'garbage'        => ['garbage'],
            'out of range'   => ['999.999.999.999'],
            'empty bits'     => ['203.0.113.0/'],
            'non-digit bits' => ['203.0.113.0/abc'],
        ];
    }
}
