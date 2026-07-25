<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Model\CashItem;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Unitpay;

final class UnitpayFormTest extends TestCase
{
    private const SECRET = 'secret';

    /**
     * @return array<string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function sign(array $params): string
    {
        return (new SignatureBuilder())->build($params, self::SECRET);
    }

    public function testFormBuildsHostedPaymentUrl(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);

        $url = $unitpay->form('public-key', 100, 'user@example.com', 'Order #1');

        $this->assertStringStartsWith('https://unitpay.ru/pay/public-key?', $url);

        $query = $this->queryOf($url);
        $this->assertSame('user@example.com', $query['account']);
        $this->assertSame('RUB', $query['currency']);
        $this->assertSame('Order #1', $query['desc']);
        $this->assertSame('100', $query['sum']);
        $this->assertSame('ru', $query['locale']);
    }

    public function testFormIncludesSignatureOverVitalParamsWhenSecretIsSet(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);

        $query = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc'));

        $this->assertArrayHasKey('signature', $query);
        $this->assertSame(
            $this->sign([
                'account'  => 'acc',
                'currency' => 'RUB',
                'desc'     => 'desc',
                'sum'      => 100,
            ]),
            $query['signature']
        );
    }

    public function testFormThrowsWithoutSecret(): void
    {
        $unitpay = new Unitpay('unitpay.ru');

        $this->expectException(UnitpayValidationException::class);
        $unitpay->form('pk', 100, 'acc', 'desc');
    }

    public function testFormHonoursCurrencyAndLocaleOverrides(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);

        $query = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc', 'USD', 'en'));

        $this->assertSame('USD', $query['currency']);
        $this->assertSame('en', $query['locale']);
    }

    public function testChainedSettersLandInTheFormUrl(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);
        $unitpay->setBackUrl('https://shop.example/back')
            ->setCustomerEmail('customer@example.com')
            ->setCustomerPhone('+79990000000')
            ->setCashItems([new CashItem('X', 1, 100.0)]);

        $query = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc'));

        $this->assertSame('https://shop.example/back', $query['backUrl']);
        $this->assertSame('customer@example.com', $query['customerEmail']);
        $this->assertSame('+79990000000', $query['customerPhone']);
        $this->assertArrayHasKey('cashItems', $query);
    }

    /**
     * form() clears the setter-accumulated params, so a reused instance does not carry
     * the previous order's backUrl/receipt/customer into the next call.
     */
    public function testFormClearsAccumulatedParamsAfterCall(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);
        $unitpay->setBackUrl('https://shop.example/back')
            ->setCustomerEmail('customer@example.com');

        $first = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc'));
        $second = $this->queryOf($unitpay->form('pk', 200, 'acc2', 'desc2'));

        $this->assertArrayHasKey('backUrl', $first);
        $this->assertArrayNotHasKey('backUrl', $second);
        $this->assertArrayNotHasKey('customerEmail', $second);
    }

    /**
     * A service call issued between the setters and form() must not swallow them: the
     * receipt and customer belong to the form, not to the lookup that ran first.
     */
    public function testFormKeepsSettersAcrossAnInterveningServiceCall(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.ru', self::SECRET, $transport);
        $unitpay->setCustomerEmail('customer@example.com')
            ->setCashItems([new CashItem('X', 1, 100.0)]);

        $unitpay->payments()->getPayment(555);
        $query = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc'));

        $this->assertStringNotContainsString('cashItems=', $transport->url(0));
        $this->assertSame('customer@example.com', $query['customerEmail']);
        $this->assertArrayHasKey('cashItems', $query);
    }

    /** The form signature must cover ONLY the four vital params, not the setter params. */
    public function testFormSignatureExcludesSetterParams(): void
    {
        $unitpay = new Unitpay('unitpay.ru', self::SECRET);
        $unitpay->setCustomerEmail('customer@example.com')
            ->setCashItems([new CashItem('X', 1, 100.0)]);

        $query = $this->queryOf($unitpay->form('pk', 100, 'acc', 'desc'));

        $this->assertSame(
            $this->sign([
                'account'  => 'acc',
                'currency' => 'RUB',
                'desc'     => 'desc',
                'sum'      => 100,
            ]),
            $query['signature']
        );
    }

    /**
     * form() adds a machine-readable sdk fingerprint token (URL-safe, PHP major.minor)
     * — and it does NOT change the signature (it sits outside the signed params).
     */
    public function testFormCarriesSdkTokenWithoutBreakingSignature(): void
    {
        $unitpay = new Unitpay('unitpay.test', self::SECRET);
        $url = $unitpay->form('pub', 100, 'order-1', 'Desc');
        $query = $this->queryOf($url);

        $this->assertSame(
            'php_' . Unitpay::VERSION . '_' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            $query['sdk']
        );
        // URL-safe: the token appears in the final URL verbatim, without %-encoding.
        $this->assertStringContainsString('sdk=php_', $url);

        $this->assertSame(
            $this->sign([
                'account'  => 'order-1',
                'currency' => 'RUB',
                'desc'     => 'Desc',
                'sum'      => 100,
            ]),
            $query['signature']
        );
    }
}
