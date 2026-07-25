<?php

namespace Tests\Api;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Exception\UnitpayTransportException;
use Unitpay\Model\CashItem;
use Unitpay\Unitpay;

/**
 * The request pipeline shared by every API service: param merging, secret resolution,
 * flat query building, JSON decoding and the transport-failure surface.
 */
final class AbstractServiceTest extends TestCase
{
    public function testRequestUrlCarriesMethodParamsAndSecret(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);

        $unitpay->payments()->getPayment(555);

        $url = $transport->lastUrl();
        $this->assertStringStartsWith('https://unitpay.test/api?', $url);
        $this->assertStringContainsString('method=getPayment', $url);
        $this->assertStringContainsString('paymentId', $url);
        $this->assertStringContainsString('555', $url);
        $this->assertStringContainsString('my-secret', $url);
    }

    public function testRequestUrlUsesFlatParamsNotNested(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);

        $unitpay->payments()->getPayment(555);

        // Unitpay accepts flat query-string params since 05/2026 — no legacy params[...] nesting.
        $url = $transport->lastUrl();
        $this->assertStringContainsString('paymentId=555', $url);
        $this->assertStringContainsString('secretKey=my-secret', $url);
        $this->assertStringNotContainsString('params%5B', $url);
        $this->assertStringNotContainsString('params[', $url);
    }

    /**
     * Params accumulated by the fluent setters (setCashItems/setCustomerEmail/…) must
     * reach the service request, not just form(). Regression guard: the pre-3.0 api() used
     * to build the URL only from its own argument and silently drop them.
     */
    public function testCashItemsFromSetterAreSentByService(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)])
            ->setCustomerEmail('buyer@example.com');

        $unitpay->payments()->initPayment('1', 100, 7, 'card');

        $url = $transport->lastUrl();
        $this->assertStringContainsString('cashItems=', $url);
        $this->assertStringContainsString('customerEmail=', $url);

        // parse_str() types query values as array|string; cashItems is always scalar here.
        $encoded = $transport->query()['cashItems'] ?? '';
        $items = json_decode(base64_decode(is_string($encoded) ? $encoded : ''), true);
        $this->assertSame('Coffee', $items[0]['name']);
    }

    /** Explicit call options take precedence over anything set by the fluent setters. */
    public function testExplicitOptionOverridesAccumulatedParam(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);
        $unitpay->setBackUrl('https://old.example/back');

        $unitpay->payments()->initPayment('1', 100, 7, 'card', [
            'backUrl' => 'https://new.example/back',
        ]);

        $this->assertSame('https://new.example/back', $transport->query()['backUrl']);
    }

    /**
     * Fluent-setter params are cleared by a successful call and must not leak into the
     * next one on a reused instance (regression: a stale cashItems receipt or
     * customerEmail would otherwise go out with an unrelated later order).
     */
    public function testFluentSetterParamsDoNotBleedIntoNextCall(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);

        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)])
            ->setCustomerEmail('buyer@example.com');
        $unitpay->payments()->initPayment('1', 100, 7, 'card');

        // The second call, without re-setting the receipt/customer, must be clean.
        $unitpay->payments()->getPayment(555);

        $this->assertStringContainsString('cashItems=', $transport->url(0));
        $this->assertStringNotContainsString('cashItems=', $transport->url(1));
        $this->assertStringNotContainsString('customerEmail=', $transport->url(1));
    }

    /**
     * A CONSUMING call clears the fluent-setter params once the request has been attempted —
     * on a transport failure too, not only on success — so a stale receipt cannot leak into
     * an unrelated later order on a reused instance. A retry must re-apply the setters
     * (symmetric with form()).
     */
    public function testFluentSetterParamsAreClearedAfterFailedConsumingCall(): void
    {
        // The first call simulates a transport failure (false), later ones succeed.
        $transport = new FakeTransport(false, '{"result":{}}');
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)]);

        try {
            $unitpay->payments()->initPayment('order-1', 100, 7, 'card');
            $this->fail('expected a transport exception on the first call');
        } catch (UnitpayTransportException $e) {
            // expected: the transport returned false
        }

        $unitpay->payments()->initPayment('order-2', 100, 7, 'card');

        $this->assertStringContainsString('cashItems=', $transport->url(0));
        $this->assertStringNotContainsString('cashItems=', $transport->url(1));
    }

    /**
     * A lookup issued between setCashItems() and initPayment() must not swallow the
     * receipt: only the calls that accept the fluent-setter params may drain them.
     */
    public function testNonConsumingCallNeitherReceivesNorConsumesPendingParams(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)])
            ->setCustomerEmail('buyer@example.com');

        $unitpay->reference()->getPartner('partner@example.com');
        $unitpay->payments()->initPayment('order-1', 100, 7, 'card');

        $this->assertStringNotContainsString('cashItems=', $transport->url(0));
        $this->assertStringNotContainsString('customerEmail=', $transport->url(0));
        $this->assertStringContainsString('cashItems=', $transport->url(1));
        $this->assertStringContainsString('customerEmail=', $transport->url(1));
    }

    /**
     * A failing non-consuming call must not eat the pending params either — the params
     * belong to the payment that has not been sent yet.
     */
    public function testFailedNonConsumingCallLeavesPendingParamsIntact(): void
    {
        $transport = new FakeTransport(false, '{"result":{}}');
        $unitpay = new Unitpay('unitpay.test', 'my-secret', $transport);
        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)]);

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected a transport exception on the first call');
        } catch (UnitpayTransportException $e) {
            // expected: the transport returned false
        }

        $unitpay->payments()->initPayment('order-1', 100, 7, 'card');

        $this->assertStringNotContainsString('cashItems=', $transport->url(0));
        $this->assertStringContainsString('cashItems=', $transport->url(1));
    }

    public function testNonObjectResponseIsReportedAsTemporaryServerError(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport('this is not json'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temporary server error');
        $unitpay->payments()->getPayment(1);
    }

    public function testMissingSecretThrows(): void
    {
        $unitpay = new Unitpay('unitpay.test', null, new FakeTransport());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $unitpay->payments()->getPayment(1);
    }

    /** A transport failure is a typed exception, still catchable as InvalidArgumentException. */
    public function testTransportFailureThrowsTypedTransportException(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport(false));

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected a transport exception');
        } catch (UnitpayTransportException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertStringContainsString('Temporary server error', $e->getMessage());
        }
    }

    /** Account-level methods can override the project key with the account key (secretKey). */
    public function testExplicitSecretKeyOverridesInstanceKey(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'project-key', $transport);

        $unitpay->reference()->getPartner('partner@example.com', ['secretKey' => 'account-key']);

        $this->assertSame('account-key', $transport->query()['secretKey']);
    }

    /**
     * Required params are now enforced by the service method signatures instead of the
     * pre-3.0 runtime REQUIRED_UNITPAY_METHODS_PARAMS dictionary. Guards against someone
     * "simplifying" the services by giving the required arguments defaults.
     */
    public function testRequiredParamsAreEnforcedByTheMethodSignature(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport());

        $this->expectException(\ArgumentCountError::class);
        /** @phpstan-ignore-next-line deliberately called with too few arguments */
        $unitpay->payments()->initPayment('order-1');
    }
}
