<?php

namespace Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Unitpay\Webhook\IpAllowlist;

/**
 * Direct tests of IP-to-allowlist matching. contains() is security-critical but is
 * otherwise exercised only indirectly through checkHandlerRequest(); here we pin down the
 * edge cases (address-family mismatch, oversized prefix length, malformed client IP)
 * that are hard to express through the full handler path.
 */
final class IpAllowlistTest extends TestCase
{
    private function matcher(): IpAllowlist
    {
        return new IpAllowlist(['31.186.100.49', '203.0.113.0/24', '2001:db8::/32']);
    }

    public function testExactIpv4AddressMatches(): void
    {
        $this->assertTrue($this->matcher()->contains('31.186.100.49'));
    }

    public function testUnlistedIpv4AddressDoesNotMatch(): void
    {
        $this->assertFalse($this->matcher()->contains('8.8.8.8'));
    }

    public function testAddressInsideIpv4CidrMatches(): void
    {
        $this->assertTrue($this->matcher()->contains('203.0.113.55'));
    }

    public function testAddressOutsideIpv4CidrDoesNotMatch(): void
    {
        $this->assertFalse($this->matcher()->contains('203.0.114.1'));
    }

    public function testAddressInsideIpv6CidrMatches(): void
    {
        $this->assertTrue($this->matcher()->contains('2001:db8::1'));
    }

    /**
     * An exact IPv6 entry matches regardless of textual form (case, compression):
     * the comparison is over the packed in_addr, not the string.
     */
    public function testExactIpv6MatchesRegardlessOfTextualForm(): void
    {
        $upper = new IpAllowlist(['2001:DB8::1']);
        $this->assertTrue($upper->contains('2001:db8::1'));

        $expanded = new IpAllowlist(['2001:db8:0:0:0:0:0:1']);
        $this->assertTrue($expanded->contains('2001:db8::1'));
    }

    /** A malformed client IP must not produce a false match. */
    public function testInvalidClientIpDoesNotMatch(): void
    {
        $this->assertFalse($this->matcher()->contains('not-an-ip'));
    }

    /**
     * An IPv4 client against an IPv6-only subnet: inet_pton yields in_addr of different
     * lengths, so the comparison must fail cleanly rather than match by mistake.
     */
    public function testIpv4ClientAgainstIpv6OnlySubnetDoesNotMatch(): void
    {
        $matcher = new IpAllowlist(['2001:db8::/32']);

        $this->assertFalse($matcher->contains('203.0.113.55'));
    }

    /** A prefix longer than the address itself (/33 for IPv4) cannot match anything. */
    public function testPrefixWiderThanAddressDoesNotMatch(): void
    {
        $matcher = new IpAllowlist(['203.0.113.0/33']);

        $this->assertFalse($matcher->contains('203.0.113.5'));
    }

    /** /25 subnet boundary: an address above the range's upper bound is not included. */
    public function testCidrBoundaryIsRespected(): void
    {
        $matcher = new IpAllowlist(['77.75.153.0/25']);

        $this->assertTrue($matcher->contains('77.75.153.127'));
        $this->assertFalse($matcher->contains('77.75.153.128'));
    }

    // --- isValidEntry() --------------------------------------------------------

    /**
     * @dataProvider validEntries
     */
    public function testIsValidEntryAcceptsWellFormedEntries(string $entry): void
    {
        $this->assertTrue(IpAllowlist::isValidEntry($entry));
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
        $this->assertFalse(IpAllowlist::isValidEntry($entry));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function invalidEntries(): array
    {
        return [
            'garbage'           => ['garbage'],
            'out of range'      => ['999.999.999.999'],
            'empty bits'        => ['203.0.113.0/'],
            'non-digit bits'    => ['203.0.113.0/abc'],
            'ipv4 bits too big' => ['203.0.113.0/33'],
            'ipv6 bits too big' => ['2001:db8::/129'],
        ];
    }

    // --- parseWebhooksFeed() ---------------------------------------------------

    public function testParseWebhooksFeedReturnsDedupedList(): void
    {
        $body = (string) json_encode(['webhooks' => ['1.1.1.1', '1.1.1.1', '2.2.2.2']]);

        $this->assertSame(['1.1.1.1', '2.2.2.2'], IpAllowlist::parseWebhooksFeed($body));
    }

    public function testParseWebhooksFeedKeepsOnlyValidEntries(): void
    {
        $body = (string) json_encode(['webhooks' => ['203.0.113.0/24', 'garbage', '2001:db8::1']]);

        $this->assertSame(['203.0.113.0/24', '2001:db8::1'], IpAllowlist::parseWebhooksFeed($body));
    }

    /**
     * @dataProvider unusableFeeds
     */
    public function testParseWebhooksFeedReturnsNullForUnusableInput(string $body): void
    {
        $this->assertNull(IpAllowlist::parseWebhooksFeed($body));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function unusableFeeds(): array
    {
        return [
            'empty string'         => [''],
            'malformed json'       => ['this is not json'],
            'missing webhooks'     => ['{"foo":1}'],
            'webhooks not array'   => ['{"webhooks":42}'],
            'only invalid entries' => ['{"webhooks":["garbage","999.999.999.999"]}'],
        ];
    }
}
