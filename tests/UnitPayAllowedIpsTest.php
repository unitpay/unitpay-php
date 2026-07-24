<?php

namespace Tests;

use UnitPay;
use UnitpayIpAllowlist;
use UnitpayIpException;
use PHPUnit\Framework\TestCase;

/**
 * Dynamic webhook IP allowlist: refreshAllowedIps() fetches the published feed
 * (https://<domain>/ips/ips_webhooks.json), addAllowedIps() adds merchant IPs on top,
 * and every path is fail-safe (never empties the list, never throws).
 */
final class UnitPayAllowedIpsTest extends TestCase
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
        $params['signature'] = (new UnitPay('unitpay.ru', self::SECRET))->getSignature($params, 'pay');

        return ['method' => 'pay', 'params' => $params];
    }

    /** A handler whose transport returns a fixed body for any URL. */
    private function handler(string $feedBody, string $ip): UnitPay
    {
        return $this->handlerWithTransport(static function () use ($feedBody) {
            return $feedBody;
        }, $ip);
    }

    /** A handler with a given transport (to simulate failures / capture the URL). */
    private function handlerWithTransport(callable $transport, string $ip): UnitPay
    {
        return new UnitPay('unitpay.ru', self::SECRET, $transport, $this->validRequest(), $ip);
    }

    /**
     * @param string[] $ips
     */
    private function feed(array $ips): string
    {
        return json_encode(['webhooks' => $ips]);
    }

    // --- replace semantics -----------------------------------------------

    public function testFetchedIpNotInDefaultBecomesAllowed(): void
    {
        $ip = '203.0.113.7'; // TEST-NET-3, not in the default list
        $unitPay = $this->handler($this->feed([$ip]), $ip);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testDefaultIpDroppedByFetchIsRejected(): void
    {
        // The feed no longer contains the built-in address → it must stop being trusted.
        $unitPay = $this->handler($this->feed(['203.0.113.7']), self::DEFAULT_IP);
        $unitPay->refreshAllowedIps();

        $this->expectException(UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    // --- fail-safety (fall back to the built-in list) --------------------

    public function testTransportFailureKeepsBuiltinList(): void
    {
        $unitPay = $this->handlerWithTransport(static function () {
            return false; // transport failure
        }, self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMalformedJsonKeepsBuiltinList(): void
    {
        $unitPay = $this->handler('this is not json', self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testMissingWebhooksKeyKeepsBuiltinList(): void
    {
        $unitPay = $this->handler('{"foo":123}', self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testEmptyFeedKeepsBuiltinList(): void
    {
        $unitPay = $this->handler($this->feed([]), self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    public function testAllInvalidEntriesKeepBuiltinList(): void
    {
        $unitPay = $this->handler($this->feed(['garbage', '999.999.999.999']), self::DEFAULT_IP);

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
        // the fallback list kept the default addresses, junk was not stored
        $this->assertContains(self::DEFAULT_IP, $unitPay->getAllowedIps());
        $this->assertNotContains('garbage', $unitPay->getAllowedIps());
    }

    // --- merchant additions on top ---------------------------------------

    public function testCustomIpSurvivesRefresh(): void
    {
        $customIp = '198.51.100.5'; // TEST-NET-2, the merchant's own relay
        $unitPay = $this->handler($this->feed(['203.0.113.7']), $customIp);

        $unitPay->addAllowedIps([$customIp])->refreshAllowedIps();

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- feed URL --------------------------------------------------------

    public function testRefreshFetchesTheCanonicalFeedUrl(): void
    {
        $captured = null;
        $unitPay = $this->handlerWithTransport(static function ($url) use (&$captured) {
            $captured = $url;
            return '{"webhooks":["203.0.113.7"]}';
        }, self::DEFAULT_IP);

        $unitPay->refreshAllowedIps();

        $this->assertSame('https://unitpay.ru/ips/ips_webhooks.json', $captured);
    }

    // --- CIDR from the feed ----------------------------------------------

    public function testCidrRangeFromFeedIsHonoured(): void
    {
        $unitPay = $this->handler($this->feed(['203.0.113.0/24']), '203.0.113.55');

        $this->assertTrue($unitPay->refreshAllowedIps()->checkHandlerRequest());
    }

    // --- junk filtering --------------------------------------------------

    public function testValidEntriesAppliedAndJunkDropped(): void
    {
        $unitPay = $this->handler($this->feed(['203.0.113.7', 'garbage']), '203.0.113.7');
        $unitPay->refreshAllowedIps();

        $this->assertSame(['203.0.113.7'], $unitPay->getAllowedIps());
        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- getAllowedIps() -----------------------------------------------------

    public function testGetAllowedIpsReturnsDedupedUnion(): void
    {
        $unitPay = new UnitPay('unitpay.ru', self::SECRET);
        $unitPay->setAllowedIps(['1.1.1.1'])->addAllowedIps(['1.1.1.1', '2.2.2.2']);

        $this->assertSame(['1.1.1.1', '2.2.2.2'], $unitPay->getAllowedIps());
    }

    public function testGetAllowedIpsDefaultsToBuiltinList(): void
    {
        $unitPay = new UnitPay('unitpay.ru', self::SECRET);

        $this->assertSame(['31.186.100.49', '51.250.20.9'], $unitPay->getAllowedIps());
    }

    /**
     * setAllowedIps([]) is fail-closed, not a no-op: an empty allowlist (with no
     * addAllowedIps() entries) rejects every webhook rather than trusting all sources.
     */
    public function testEmptyAllowlistRejectsEveryWebhook(): void
    {
        $unitPay = new UnitPay('unitpay.ru', self::SECRET, null, $this->validRequest(), self::DEFAULT_IP);
        $unitPay->setAllowedIps([]);

        $this->assertSame([], $unitPay->getAllowedIps());
        $this->expectException(UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    // --- matcher cache reset ---------------------------------------------

    public function testAddAllowedIpsInvalidatesTheMatcherCache(): void
    {
        $customIp = '198.51.100.5';
        $unitPay = $this->handler($this->feed([self::DEFAULT_IP]), $customIp);

        // The first check builds and caches the matcher without the added IP → rejection.
        try {
            $unitPay->checkHandlerRequest();
            $this->fail('expected the custom IP to be rejected before it is added');
        } catch (UnitpayIpException $e) {
            // expected
        }

        // Adding the IP must reset the matcher cache so the next check sees it.
        $unitPay->addAllowedIps([$customIp]);
        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    // --- UnitpayIpAllowlist::isValidEntry() ----------------------------------

    /**
     * @dataProvider validEntries
     */
    public function testIsValidEntryAcceptsWellFormedEntries(string $entry): void
    {
        $this->assertTrue(UnitpayIpAllowlist::isValidEntry($entry));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function validEntries(): array
    {
        return [
            'ipv4'      => ['31.186.100.49'],
            'ipv6'      => ['2001:db8::1'],
            'ipv4 cidr' => ['203.0.113.0/24'],
            'ipv6 cidr' => ['2001:db8::/32'],
        ];
    }

    /**
     * @dataProvider invalidEntries
     */
    public function testIsValidEntryRejectsMalformedEntries(string $entry): void
    {
        $this->assertFalse(UnitpayIpAllowlist::isValidEntry($entry));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function invalidEntries(): array
    {
        return [
            'garbage'          => ['garbage'],
            'out of range'     => ['999.999.999.999'],
            'empty bits'       => ['203.0.113.0/'],
            'non-digit bits'   => ['203.0.113.0/abc'],
            'ipv4 bits too big' => ['203.0.113.0/33'],
            'ipv6 bits too big' => ['2001:db8::/129'],
        ];
    }
}
