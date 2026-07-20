<?php

namespace Tests;

use InvalidArgumentException;
use UnexpectedValueException;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayHandlerTest extends TestCase
{
    public const SECRET = 'secret';
    public const ALLOWED_IP = '31.186.100.49';

    /**
     * Строит массив вебхука с корректной подписью его параметров.
     *
     * @param string $method
     * @param array  $overrides параметры для добавления/переопределения перед подписью
     * @return array{method: string, params: array}
     */
    private function validRequest($method = 'pay', array $overrides = [])
    {
        $params = array_merge([
            'account'       => '42',
            'orderSum'      => '100.00',
            'orderCurrency' => 'RUB',
            'date'          => '2026-07-20 12:00:00',
            'payerSum'      => '100.00',
            'unitpayId'     => '999',
        ], $overrides);

        $params['signature'] = $this->sign($params, $method);

        return ['method' => $method, 'params' => $params];
    }

    private function sign(array $params, $method)
    {
        return (new UnitPay('unitpay.ru', self::SECRET))->getSignature($params, $method);
    }

    private function handler(array $request, $ip = self::ALLOWED_IP, $secret = self::SECRET)
    {
        return new UnitPay('unitpay.ru', $secret, null, $request, $ip);
    }

    public function testValidSignatureAndAllowedIpPass()
    {
        $this->assertTrue($this->handler($this->validRequest('pay'))->checkHandlerRequest());
    }

    public function testTamperedParamsAreRejected()
    {
        $request = $this->validRequest('pay');
        $request['params']['orderSum'] = '0.01'; // изменено после подписи

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong signature');
        $this->handler($request)->checkHandlerRequest();
    }

    public function testDisallowedIpIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IP address Error');
        $this->handler($this->validRequest('pay'), '8.8.8.8')->checkHandlerRequest();
    }

    public function testEmptySecretIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $this->handler($this->validRequest('pay'), self::ALLOWED_IP, null)->checkHandlerRequest();
    }

    public function testMissingMethodIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Method is null');
        $this->handler(['params' => ['x' => '1']])->checkHandlerRequest();
    }

    public function testMissingParamsIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Params is null');
        $this->handler(['method' => 'pay'])->checkHandlerRequest();
    }

    public function testUnsupportedPartnerMethodIsRejected()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Method is not supported');
        $this->handler($this->validRequest('refund'))->checkHandlerRequest();
    }

    /**
     * Уведомление о двухстадийной блокировке (preauth) — валидный метод вебхука,
     * который шлёт Unitpay (method = check | pay | preauth | error). Должно проходить
     * проверку, а не отклоняться как неподдерживаемое.
     */
    public function testPreauthPartnerMethodIsSupported()
    {
        $request = $this->validRequest('preauth', ['isPreauth' => '1']);

        $unitPay = $this->handler($request);

        $this->assertTrue($unitPay->checkHandlerRequest());
        $this->assertSame('preauth', $unitPay->getHandlerMethod());
    }

    /**
     * Нестроковая подпись (например, массив, подсунутый через $_GET) должна быть
     * аккуратно отклонена как "Wrong signature", а не приводить к TypeError.
     */
    public function testArraySignatureIsRejectedCleanly()
    {
        $request = $this->validRequest('pay');
        $request['params']['signature'] = ['not', 'a', 'string'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong signature');
        $this->handler($request)->checkHandlerRequest();
    }

    /**
     * Подделанный params[PHP_INT_MAX] не должен ломать проверку и должен проходить
     * её корректно (ключ убирается и при подписи, и при проверке).
     */
    public function testPhpIntMaxKeyInParamsDoesNotBreakVerification()
    {
        $params = [
            'account'       => '42',
            'orderSum'      => '100.00',
            'orderCurrency' => 'RUB',
        ];
        $params[PHP_INT_MAX] = 'injected';
        $params['signature'] = $this->sign($params, 'pay');

        $request = ['method' => 'pay', 'params' => $params];

        $this->assertTrue($this->handler($request)->checkHandlerRequest());
    }

    public function testSetAllowedIpsOverridesTheDefaultAllowlist()
    {
        $customIp = '203.0.113.7'; // TEST-NET-3, нет в списке по умолчанию
        $unitPay = $this->handler($this->validRequest('pay'), $customIp);
        $unitPay->setAllowedIps([$customIp]);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    /** 127.0.0.1 по умолчанию НЕ доверенный: за прокси на том же хосте он обнулил бы проверку IP. */
    public function testLocalhostIsRejectedByDefault()
    {
        $unitPay = $this->handler($this->validRequest('pay'), '127.0.0.1');

        $this->expectException(\UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    /** setAllowedIps принимает CIDR-подсети, а не только точные IP. */
    public function testCidrAllowlistMatchesAddressInRange()
    {
        $unitPay = $this->handler($this->validRequest('pay'), '203.0.113.55');
        $unitPay->setAllowedIps(['203.0.113.0/24']);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    public function testCidrAllowlistRejectsAddressOutOfRange()
    {
        $unitPay = $this->handler($this->validRequest('pay'), '203.0.114.1');
        $unitPay->setAllowedIps(['203.0.113.0/24']);

        $this->expectException(\UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    /** Сопоставление CIDR работает и для IPv6 (бинарное сравнение через inet_pton). */
    public function testCidrAllowlistMatchesIpv6InRange()
    {
        $unitPay = $this->handler($this->validRequest('pay'), '2001:db8::1');
        $unitPay->setAllowedIps(['2001:db8::/32']);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    /** Типизированное исключение, всё ещё наследующее исторический SPL-тип + маркерный интерфейс. */
    public function testSignatureFailureThrowsTypedExceptionStillCatchableAsInvalidArgument()
    {
        $request = $this->validRequest('pay');
        $request['params']['orderSum'] = '0.01'; // изменено после подписи

        try {
            $this->handler($request)->checkHandlerRequest();
            $this->fail('expected a signature exception');
        } catch (\UnitpaySignatureException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertInstanceOf(\UnitpayExceptionInterface::class, $e);
        }
    }
}
