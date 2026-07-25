<?php

namespace Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Exception\UnitpayIpException;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Unitpay;
use Unitpay\Webhook\WebhookVerifier;

/**
 * Dynamic webhook IP allowlist: refreshAllowedIps() fetches the published feed
 * (https://<domain>/ips/ips_webhooks.json), addAllowedIps() adds merchant IPs on top,
 * and every path is fail-safe (never empties the list, never throws).
 */
final class AllowedIpsTest extends TestCase
{
    private const SECRET = 'secret';
    /** One of the built-in default addresses. */
    private const DEFAULT_IP = '31.186.100.49';

    /**
     * Builds a valid signed 'pay' webhook.
     *
     * @return array{method: string, params: array<string, string>}
     */
    private function validRequest(): array
    {
        $params = [
            'account'   => '42',
            'orderSum'  => '100.00',
            'unitpayId' => '999',
        ];
        $params['signature'] = (new SignatureBuilder())->build($params, self::SECRET, 'pay');

        return ['method' => 'pay', 'params' => $params];
    }

    /** A verifier whose transport returns a fixed body for any URL. */
    private function handler(string $feedBody, string $ip): WebhookVerifier
    {
        return $this->handlerWithTransport(new FakeTransport($feedBody), $ip);
    }

    /** A verifier with a given transport (to simulate failures / capture the URL). */
    private function handlerWithTransport(FakeTransport $transport, string $ip): WebhookVerifier
    {
        return (new Unitpay('unitpay.ru', self::SECRET, $transport, $this->validRequest(), $ip))->webhook();
    }

    /**
     * @param string[] $ips
     */
    private function feed(array $ips): string
    {
        return (string) json_encode(['webhooks' => $ips]);
    }

    // --- replace semantics -----------------------------------------------

    public function testFetchedIpNotInDefaultBecomesAllowed(): void
    {
        $ip = '203.0.113.7'; // TEST-NET-3, not in the default list
        $webhook = $this->handler($this->feed([$ip]), $ip);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testDefaultIpDroppedByFetchIsRejected(): void
    {
        // The feed no longer contains the built-in address → it must stop being trusted.
        $webhook = $this->handler($this->feed(['203.0.113.7']), self::DEFAULT_IP);
        $webhook->refreshAllowedIps();

        $this->expectException(UnitpayIpException::class);
        $webhook->checkHandlerRequest();
    }

    // --- fail-safety (fall back to the built-in list) --------------------

    public function testTransportFailureKeepsBuiltinList(): void
    {
        $webhook = $this->handlerWithTransport(new FakeTransport(false), self::DEFAULT_IP);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMalformedJsonKeepsBuiltinList(): void
    {
        $webhook = $this->handler('this is not json', self::DEFAULT_IP);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMissingWebhooksKeyKeepsBuiltinList(): void
    {
        $webhook = $this->handler('{"foo":123}', self::DEFAULT_IP);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testEmptyFeedKeepsBuiltinList(): void
    {
        $webhook = $this->handler($this->feed([]), self::DEFAULT_IP);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testAllInvalidEntriesKeepBuiltinList(): void
    {
        $webhook = $this->handler($this->feed(['garbage', '999.999.999.999']), self::DEFAULT_IP);

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
        // the fallback list kept the default addresses, junk was not stored
        $this->assertContains(self::DEFAULT_IP, $webhook->getAllowedIps());
        $this->assertNotContains('garbage', $webhook->getAllowedIps());
    }

    // --- merchant additions on top ---------------------------------------

    public function testCustomIpSurvivesRefresh(): void
    {
        $customIp = '198.51.100.5'; // TEST-NET-2, the merchant's own relay
        $webhook = $this->handler($this->feed(['203.0.113.7']), $customIp);

        $webhook->addAllowedIps([$customIp])->refreshAllowedIps();

        $this->assertTrue($webhook->checkHandlerRequest());
    }

    // --- feed URL --------------------------------------------------------

    public function testRefreshFetchesTheCanonicalFeedUrl(): void
    {
        $transport = new FakeTransport('{"webhooks":["203.0.113.7"]}');
        $webhook = $this->handlerWithTransport($transport, self::DEFAULT_IP);

        $webhook->refreshAllowedIps();

        $this->assertSame('https://unitpay.ru/ips/ips_webhooks.json', $transport->lastUrl());
    }

    // --- CIDR from the feed ----------------------------------------------

    public function testCidrRangeFromFeedIsHonoured(): void
    {
        $webhook = $this->handler($this->feed(['203.0.113.0/24']), '203.0.113.55');

        $this->assertTrue($webhook->refreshAllowedIps()->checkHandlerRequest());
    }

    // --- junk filtering --------------------------------------------------

    public function testValidEntriesAppliedAndJunkDropped(): void
    {
        $webhook = $this->handler($this->feed(['203.0.113.7', 'garbage']), '203.0.113.7');
        $webhook->refreshAllowedIps();

        $this->assertSame(['203.0.113.7'], $webhook->getAllowedIps());
        $this->assertTrue($webhook->checkHandlerRequest());
    }

    // --- getAllowedIps() -----------------------------------------------------

    public function testGetAllowedIpsReturnsDedupedUnion(): void
    {
        $webhook = (new Unitpay('unitpay.ru', self::SECRET))->webhook();
        $webhook->setAllowedIps(['1.1.1.1'])->addAllowedIps(['1.1.1.1', '2.2.2.2']);

        $this->assertSame(['1.1.1.1', '2.2.2.2'], $webhook->getAllowedIps());
    }

    public function testGetAllowedIpsDefaultsToBuiltinList(): void
    {
        $webhook = (new Unitpay('unitpay.ru', self::SECRET))->webhook();

        $this->assertSame(['31.186.100.49', '51.250.20.9'], $webhook->getAllowedIps());
    }

    /**
     * setAllowedIps([]) is fail-closed, not a no-op: an empty allowlist (with no
     * addAllowedIps() entries) rejects every webhook rather than trusting all sources.
     */
    public function testEmptyAllowlistRejectsEveryWebhook(): void
    {
        $webhook = (new Unitpay('unitpay.ru', self::SECRET, null, $this->validRequest(), self::DEFAULT_IP))->webhook();
        $webhook->setAllowedIps([]);

        $this->assertSame([], $webhook->getAllowedIps());
        $this->expectException(UnitpayIpException::class);
        $webhook->checkHandlerRequest();
    }

    // --- matcher cache reset ---------------------------------------------

    public function testAddAllowedIpsInvalidatesTheMatcherCache(): void
    {
        $customIp = '198.51.100.5';
        $webhook = $this->handler($this->feed([self::DEFAULT_IP]), $customIp);

        // The first check builds and caches the matcher without the added IP → rejection.
        try {
            $webhook->checkHandlerRequest();
            $this->fail('expected the custom IP to be rejected before it is added');
        } catch (UnitpayIpException $e) {
            // expected
        }

        // Adding the IP must reset the matcher cache so the next check sees it.
        $webhook->addAllowedIps([$customIp]);
        $this->assertTrue($webhook->checkHandlerRequest());
    }
}
