<?php

namespace Tests\Model\Enum;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Model\Enum\PaymentType;
use Unitpay\Unitpay;

/**
 * The PaymentType constants must stay in sync with Unitpay's published payment
 * method codes: https://help.unitpay.ru/book-of-reference/payment-system-codes
 * Deprecated codes (qiwi, yandex, mc, alfaClick) are deliberately absent.
 */
final class PaymentTypeTest extends TestCase
{
    public function testPaymentTypeConstantsMatchPublishedCodes(): void
    {
        $this->assertSame('card', PaymentType::CARD);
        $this->assertSame('cardInvoice', PaymentType::CARD_INVOICE);
        $this->assertSame('sbp', PaymentType::SBP);
        $this->assertSame('sberpay', PaymentType::SBERPAY);
        $this->assertSame('tinkoffpay', PaymentType::TINKOFFPAY);
        $this->assertSame('paypal', PaymentType::PAYPAL);
        $this->assertSame('webmoney', PaymentType::WEBMONEY);
    }

    /** A payment method constant is accepted as-is as the paymentType for initPayment. */
    public function testConstantIsUsableAsInitPaymentType(): void
    {
        $transport = new FakeTransport(
            (string) json_encode(['result' => ['type' => 'redirect', 'redirectUrl' => 'https://unitpay.ru/pay']])
        );
        $unitpay = new Unitpay('unitpay.ru', 'secret', $transport);

        $unitpay->payments()->initPayment('order-1', 100, 1, PaymentType::CARD);

        $this->assertStringContainsString('paymentType=card', $transport->lastUrl());
    }
}
