<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Model\CashItem;
use Unitpay\Unitpay;

final class PaymentServiceTest extends TestCase
{
    private function unitpay(FakeTransport $transport): Unitpay
    {
        return new Unitpay('unitpay.test', 'secret', $transport);
    }

    public function testInitPaymentReturnsDecodedResponseViaInjectedTransport(): void
    {
        $unitpay = $this->unitpay(new FakeTransport('{"result":{"receiptId":42}}'));

        $response = $unitpay->payments()->initPayment('1', 100, 7, 'card');

        $this->assertSame(
            ['result' => ['receiptId' => 42]],
            json_decode((string) json_encode($response), true)
        );
    }

    public function testInitPaymentSendsItsRequiredParams(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->initPayment('order-1', 100, 7, 'card');

        $query = $transport->query();
        $this->assertSame('initPayment', $query['method']);
        $this->assertSame('order-1', $query['account']);
        $this->assertSame('100', $query['sum']);
        $this->assertSame('7', $query['projectId']);
        $this->assertSame('card', $query['paymentType']);
    }

    /** desc is optional since the required params were aligned with the backend. */
    public function testInitPaymentPassesOptionsThrough(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->initPayment('order-1', 100, 7, 'card', [
            'desc'     => 'Order #1',
            'currency' => 'USD',
        ]);

        $query = $transport->query();
        $this->assertSame('Order #1', $query['desc']);
        $this->assertSame('USD', $query['currency']);
    }

    public function testGetPaymentSendsPaymentId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->getPayment(555);

        $this->assertSame('getPayment', $transport->query()['method']);
        $this->assertSame('555', $transport->query()['paymentId']);
    }

    /** A refund is full by default and partial when a sum is passed. */
    public function testRefundPaymentSupportsPartialSum(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->refundPayment(555, ['sum' => 50]);

        $query = $transport->query();
        $this->assertSame('refundPayment', $query['method']);
        $this->assertSame('555', $query['paymentId']);
        $this->assertSame('50', $query['sum']);
    }

    public function testConfirmPaymentSendsPaymentId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->confirmPayment(555);

        $this->assertSame('confirmPayment', $transport->query()['method']);
        $this->assertSame('555', $transport->query()['paymentId']);
    }

    public function testCancelPaymentSendsPaymentId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->cancelPayment(555);

        $this->assertSame('cancelPayment', $transport->query()['method']);
        $this->assertSame('555', $transport->query()['paymentId']);
    }

    public function testOffsetAdvanceSendsLoginAndPaymentId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payments()->offsetAdvance('partner@example.com', 555);

        $query = $transport->query();
        $this->assertSame('offsetAdvance', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
        $this->assertSame('555', $query['paymentId']);
    }

    /**
     * offsetAdvance is a consuming call: the advance-offset receipt is optional, but when
     * it is set via setCashItems() it must reach the request and be cleared afterwards.
     */
    public function testOffsetAdvanceCarriesTheAccumulatedReceipt(): void
    {
        $transport = new FakeTransport();
        $unitpay = $this->unitpay($transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)]);

        $unitpay->payments()->offsetAdvance('partner@example.com', 555);
        $unitpay->payments()->offsetAdvance('partner@example.com', 556);

        $this->assertStringContainsString('cashItems=', $transport->url(0));
        $this->assertStringNotContainsString('cashItems=', $transport->url(1));
    }

    /** getPayment does not accept a receipt, so it must never carry one. */
    public function testGetPaymentNeverCarriesTheAccumulatedReceipt(): void
    {
        $transport = new FakeTransport();
        $unitpay = $this->unitpay($transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)]);

        $unitpay->payments()->getPayment(555);

        $this->assertStringNotContainsString('cashItems=', $transport->url(0));
    }
}
