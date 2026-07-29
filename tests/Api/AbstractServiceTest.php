<?php

namespace Tests\Api;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Exception\UnitpayHttpException;
use Unitpay\Exception\UnitpayNetworkException;
use Unitpay\Exception\UnitpayResponseException;
use Unitpay\Exception\UnitpayTransportException;
use Unitpay\Http\Response;
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

    public function testMissingSecretThrows(): void
    {
        $unitpay = new Unitpay('unitpay.test', null, new FakeTransport());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $unitpay->payments()->getPayment(1);
    }

    /**
     * A 2xx whose body is not a JSON object is a protocol problem, not a network one:
     * the server answered, it just did not answer with what the API promises.
     */
    public function testNonJsonBodyThrowsResponseException(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport('this is not json'));

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected a response exception');
        } catch (UnitpayResponseException $e) {
            $this->assertSame('this is not json', $e->getResponseBody());
            $this->assertInstanceOf(UnitpayTransportException::class, $e);
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }

    /** A connect-phase failure never reached the server; the message must say so. */
    public function testConnectFailureThrowsNetworkException(): void
    {
        $transport = new FakeTransport(Response::failed(7, 'Failed to connect to unitpay.test port 443', false));
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected a network exception');
        } catch (UnitpayNetworkException $e) {
            $this->assertSame(7, $e->getErrno());
            $this->assertNotNull($e->getTransportError());
            $this->assertStringContainsString('Failed to connect', (string) $e->getTransportError());
            $this->assertStringContainsString('was not sent', $e->getMessage());
        }
    }

    /**
     * A read timeout carries the same cURL errno as a connect timeout but a completely
     * different consequence: the server saw the request and may already have created the
     * payment. The message has to say that out loud — it is the difference between "retry
     * safely" and "check before retrying".
     */
    public function testReadTimeoutSaysTheRequestMayHaveBeenProcessed(): void
    {
        $transport = new FakeTransport(Response::failed(28, 'Operation timed out after 10001 milliseconds', true));
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        try {
            $unitpay->payments()->initPayment('order-1', 100, 7, 'card');
            $this->fail('expected a network exception');
        } catch (UnitpayNetworkException $e) {
            $this->assertSame(28, $e->getErrno());
            $this->assertStringContainsString('may already have been processed', $e->getMessage());
            $this->assertStringNotContainsString('was not sent', $e->getMessage());
        }
    }

    public function testHttp404ThrowsHttpExceptionCarryingTheStatus(): void
    {
        $transport = new FakeTransport(Response::received(404, '{"error":{"message":"Not found"}}'));
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected an http exception');
        } catch (UnitpayHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('404', $e->getMessage());
        }
    }

    /**
     * A 500 with an HTML error page used to be indistinguishable from a timeout. The body
     * is what an integrator needs to quote in a support ticket, so it must survive.
     */
    public function testHttp500CarriesTheResponseBody(): void
    {
        $html = '<html><body><h1>502 Bad Gateway</h1></body></html>';
        $transport = new FakeTransport(Response::received(500, $html));
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected an http exception');
        } catch (UnitpayHttpException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertSame($html, $e->getResponseBody());
        }
    }

    /**
     * The five cases above are distinct classes now, but a caller that only wants "the
     * request failed" must still get away with a single catch — and with the pre-4.0
     * InvalidArgumentException catch it may already have.
     *
     * @dataProvider transportFailures
     * @param string|false|Response $result
     */
    public function testEveryTransportFailureIsStillOneCatch($result): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport($result));

        try {
            $unitpay->payments()->getPayment(1);
            $this->fail('expected a transport exception');
        } catch (UnitpayTransportException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertStringNotContainsString('Temporary server error', $e->getMessage());
        }
    }

    /** @return array<string, array{string|false|Response}> */
    public function transportFailures(): array
    {
        return [
            'connect failure' => [Response::failed(7, 'Failed to connect', false)],
            'read timeout' => [Response::failed(28, 'Operation timed out', true)],
            'http 404' => [Response::received(404, '')],
            'http 500' => [Response::received(500, '<html>oops</html>')],
            'non-json 200' => ['this is not json'],
        ];
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
