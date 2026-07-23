<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use UnitPay;

/**
 * The PAYMENT_TYPE_* constants must stay in sync with Unitpay's published payment
 * method codes: https://help.unitpay.ru/book-of-reference/payment-system-codes
 * Deprecated codes (qiwi, yandex, mc, alfaClick) are deliberately absent.
 */
final class UnitPayPaymentTypeTest extends TestCase
{
    public function testPaymentTypeConstantsMatchPublishedCodes(): void
    {
        $this->assertSame('card', UnitPay::PAYMENT_TYPE_CARD);
        $this->assertSame('cardInvoice', UnitPay::PAYMENT_TYPE_CARD_INVOICE);
        $this->assertSame('sbp', UnitPay::PAYMENT_TYPE_SBP);
        $this->assertSame('sberpay', UnitPay::PAYMENT_TYPE_SBERPAY);
        $this->assertSame('tinkoffpay', UnitPay::PAYMENT_TYPE_TINKOFFPAY);
        $this->assertSame('paypal', UnitPay::PAYMENT_TYPE_PAYPAL);
        $this->assertSame('webmoney', UnitPay::PAYMENT_TYPE_WEBMONEY);
    }

    /** A payment method constant is accepted as-is as the paymentType for initPayment. */
    public function testConstantIsUsableAsInitPaymentType(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return json_encode(['result' => ['type' => 'redirect', 'redirectUrl' => 'https://unitpay.ru/pay']]);
        };
        $unitpay = new UnitPay('unitpay.ru', 'secret', $transport);

        $unitpay->api('initPayment', [
            'account'     => 'order-1',
            'sum'         => 100,
            'projectId'   => 1,
            'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
        ]);

        $this->assertStringContainsString('paymentType=card', $captured);
    }
}
