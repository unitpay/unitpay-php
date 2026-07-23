<?php

namespace Tests;

use CashItem;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayFormTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        return $q;
    }

    public function testFormBuildsHostedPaymentUrl(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');

        $url = $unitPay->form('public-key', 100, 'user@example.com', 'Order #1');

        $this->assertStringStartsWith('https://unitpay.ru/pay/public-key?', $url);

        $q = $this->queryOf($url);
        $this->assertSame('user@example.com', $q['account']);
        $this->assertSame('RUB', $q['currency']);
        $this->assertSame('Order #1', $q['desc']);
        $this->assertSame('100', $q['sum']);
        $this->assertSame('ru', $q['locale']);
    }

    public function testFormIncludesSignatureOverVitalParamsWhenSecretIsSet(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');

        $q = $this->queryOf($unitPay->form('pk', 100, 'acc', 'desc'));

        $this->assertArrayHasKey('signature', $q);
        $this->assertSame(
            $unitPay->getSignature([
                'account'  => 'acc',
                'currency' => 'RUB',
                'desc'     => 'desc',
                'sum'      => 100,
            ]),
            $q['signature']
        );
    }

    public function testFormThrowsWithoutSecret(): void
    {
        $unitPay = new UnitPay('unitpay.ru');

        $this->expectException(\UnitpayValidationException::class);
        $unitPay->form('pk', 100, 'acc', 'desc');
    }

    public function testFormHonoursCurrencyAndLocaleOverrides(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');

        $q = $this->queryOf($unitPay->form('pk', 100, 'acc', 'desc', 'USD', 'en'));

        $this->assertSame('USD', $q['currency']);
        $this->assertSame('en', $q['locale']);
    }

    public function testChainedSettersLandInTheFormUrl(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setBackUrl('https://shop.example/back')
            ->setCustomerEmail('customer@example.com')
            ->setCustomerPhone('+79990000000')
            ->setCashItems([new CashItem('X', 1, 100.0)]);

        $q = $this->queryOf($unitPay->form('pk', 100, 'acc', 'desc'));

        $this->assertSame('https://shop.example/back', $q['backUrl']);
        $this->assertSame('customer@example.com', $q['customerEmail']);
        $this->assertSame('+79990000000', $q['customerPhone']);
        $this->assertArrayHasKey('cashItems', $q);
    }

    /**
     * form() очищает накопленные сеттерами параметры, поэтому повторно используемый
     * экземпляр не переносит backUrl/чек/покупателя предыдущего заказа в следующий вызов.
     */
    public function testFormClearsAccumulatedParamsAfterCall(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setBackUrl('https://shop.example/back')
            ->setCustomerEmail('customer@example.com');

        $first = $this->queryOf($unitPay->form('pk', 100, 'acc', 'desc'));
        $second = $this->queryOf($unitPay->form('pk', 200, 'acc2', 'desc2'));

        $this->assertArrayHasKey('backUrl', $first);
        $this->assertArrayNotHasKey('backUrl', $second);
        $this->assertArrayNotHasKey('customerEmail', $second);
    }

    /** Подпись формы должна покрывать ТОЛЬКО четыре ключевых параметра, а не параметры сеттеров. */
    public function testFormSignatureExcludesSetterParams(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setCustomerEmail('customer@example.com')
            ->setCashItems([new CashItem('X', 1, 100.0)]);

        $q = $this->queryOf($unitPay->form('pk', 100, 'acc', 'desc'));

        $expected = (new UnitPay('unitpay.ru', 'secret'))->getSignature([
            'account'  => 'acc',
            'currency' => 'RUB',
            'desc'     => 'desc',
            'sum'      => 100,
        ]);
        $this->assertSame($expected, $q['signature']);
    }

    /**
     * Слой A: form() добавляет машиночитаемый токен фингерпринта sdk (URL-safe,
     * major.minor PHP) — и он НЕ меняет подпись (стоит вне подписываемых параметров).
     */
    public function testFormCarriesSdkTokenWithoutBreakingSignature(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret');
        $url = $unitPay->form('pub', 100, 'order-1', 'Desc');
        $q = $this->queryOf($url);

        $this->assertSame(
            'php_' . UnitPay::VERSION . '_' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            $q['sdk']
        );
        // URL-safe: токен появляется в готовом URL дословно, без %-кодирования.
        $this->assertStringContainsString('sdk=php_', $url);

        $expected = (new UnitPay('unitpay.test', 'secret'))->getSignature([
            'account'  => 'order-1',
            'currency' => 'RUB',
            'desc'     => 'Desc',
            'sum'      => 100,
        ]);
        $this->assertSame($expected, $q['signature']);
    }
}
