<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Api\PaymentService;
use Unitpay\Api\PayoutService;
use Unitpay\Api\ReferenceService;
use Unitpay\Api\SubscriptionService;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\CurlTransport;
use Unitpay\Http\RetryingTransport;
use Unitpay\Model\CashItem;
use Unitpay\Unitpay;
use Unitpay\Webhook\WebhookVerifier;

/**
 * The composition root itself: the service getters it hands out, and the fact that one
 * injected transport and one pending-params holder are shared by everything it builds.
 */
final class UnitpayFacadeTest extends TestCase
{
    public function testServiceGettersReturnTheirServices(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport());

        $this->assertInstanceOf(PaymentService::class, $unitpay->payments());
        $this->assertInstanceOf(SubscriptionService::class, $unitpay->subscriptions());
        $this->assertInstanceOf(PayoutService::class, $unitpay->payouts());
        $this->assertInstanceOf(ReferenceService::class, $unitpay->reference());
        $this->assertInstanceOf(WebhookVerifier::class, $unitpay->webhook());
    }

    /** Services are built lazily and then reused — a getter is not a factory. */
    public function testServiceGettersAreMemoized(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport());

        $this->assertSame($unitpay->payments(), $unitpay->payments());
        $this->assertSame($unitpay->subscriptions(), $unitpay->subscriptions());
        $this->assertSame($unitpay->payouts(), $unitpay->payouts());
        $this->assertSame($unitpay->reference(), $unitpay->reference());
        $this->assertSame($unitpay->webhook(), $unitpay->webhook());
    }

    /**
     * One injected transport serves both the API service layer and the webhook IP-feed
     * fetch, so a consumer can swap the HTTP stack (or stub it in tests) in one place.
     */
    public function testInjectedTransportIsSharedByServicesAndWebhook(): void
    {
        $transport = new FakeTransport('{"result":{}}', '{"webhooks":["203.0.113.7"]}');
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->payments()->getPayment(1);
        $unitpay->webhook()->refreshAllowedIps();

        $this->assertSame(2, $transport->callCount());
        $this->assertStringStartsWith('https://unitpay.test/api?', $transport->url(0));
        $this->assertSame('https://unitpay.test/ips/ips_webhooks.json', $transport->url(1));
    }

    public function testAllServicesShareTheInjectedTransport(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->payments()->getPayment(1);
        $unitpay->subscriptions()->getSubscription(2);
        $unitpay->payouts()->massPaymentCommissions('partner@example.com');
        $unitpay->reference()->getPartner('partner@example.com');

        $this->assertSame(4, $transport->callCount());
    }

    /**
     * The pending-params holder is shared across services rather than per-service, so a
     * receipt set on the facade reaches PaymentService — but only the calls that accept it:
     * a payout lookup in between neither receives nor consumes it.
     */
    public function testAccumulatedParamsReachTheConsumingServiceOnly(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->setCashItems([new CashItem('Coffee', 1, 100.0)]);
        $unitpay->payouts()->massPaymentCommissions('partner@example.com');
        $unitpay->payments()->initPayment('order-1', 100, 7, 'card');

        $this->assertArrayNotHasKey('cashItems', $transport->query(0));
        $this->assertArrayHasKey('cashItems', $transport->query(1));
    }

    /**
     * @return array<string, array{string}>
     */
    public function validDomainProvider(): array
    {
        return [
            'production'      => ['unitpay.ru'],
            'test tld'        => ['unitpay.test'],
            'subdomain'       => ['sandbox.unitpay.ru'],
            'single label'    => ['localhost'],
            'host with port'  => ['localhost:8080'],
            'hyphenated'      => ['my-shop.example.com'],
        ];
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function testConstructorAcceptsBareHost(string $domain): void
    {
        $this->assertInstanceOf(Unitpay::class, new Unitpay($domain, 'secret'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidDomainProvider(): array
    {
        return [
            'with scheme'     => ['https://unitpay.ru'],
            'scheme only'     => ['http://'],
            'with path'       => ['unitpay.ru/api'],
            'with query'      => ['unitpay.ru?x=1'],
            'with fragment'   => ['unitpay.ru#frag'],
            'with userinfo'   => ['user@unitpay.ru'],
            'leading space'   => [' unitpay.ru'],
            'trailing space'  => ['unitpay.ru '],
            'empty'           => [''],
            'hyphen edged'    => ['-unitpay.ru'],
            'double dot'      => ['unitpay..ru'],
        ];
    }

    /**
     * A scheme or path used to sail through and surface later as a transport error or an
     * allowlist that silently failed to refresh.
     *
     * @dataProvider invalidDomainProvider
     */
    public function testConstructorRejectsAnythingButABareHost(string $domain): void
    {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('Domain must be a bare host');
        new Unitpay($domain, 'secret');
    }

    /** The domain given to the constructor drives every endpoint the facade builds. */
    public function testDomainDrivesFormAndApiEndpoints(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $formUrl = $unitpay->form('pk', 100, 'acc', 'desc');
        $unitpay->payments()->getPayment(1);

        $this->assertStringStartsWith('https://unitpay.test/pay/pk?', $formUrl);
        $this->assertStringStartsWith('https://unitpay.test/api?', $transport->lastUrl());
    }

    /** Without an injected transport the facade still builds — it falls back to the default stack. */
    public function testFacadeIsUsableWithoutAnInjectedTransport(): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret');

        $this->assertInstanceOf(PaymentService::class, $unitpay->payments());
        $this->assertStringStartsWith('https://unitpay.test/pay/pk?', $unitpay->form('pk', 100, 'acc', 'desc'));
    }

    /**
     * Retries being on by default is a promise made in the CHANGELOG and in
     * docs/getting-started.md, and it lives in exactly one line of wiring. Every other test
     * injects a FakeTransport, so replacing DefaultTransport::create() with a bare
     * `new CurlTransport()` would leave the whole suite green while the promise quietly
     * stopped being true. This is the assertion that notices.
     *
     * Reflection rather than a public getter: which transport the facade composed is an
     * implementation detail, and exposing it just to test it would put it in the API.
     */
    public function testFacadeDefaultsToTheRetryingStack(): void
    {
        $property = new \ReflectionProperty(Unitpay::class, 'transport');
        if (\PHP_VERSION_ID < 80100) {
            // Required before 8.1, and deprecated from 8.5 — call it only where it does something.
            $property->setAccessible(true);
        }

        $transport = $property->getValue(new Unitpay('unitpay.test', 'secret'));

        $this->assertInstanceOf(RetryingTransport::class, $transport);
        $this->assertInstanceOf(CurlTransport::class, $transport->getInner());
    }
}
