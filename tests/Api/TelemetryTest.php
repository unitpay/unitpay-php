<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Unitpay;

/**
 * Passive, anonymous SDK self-identification sent with every service call. No PII and
 * no dedicated telemetry request — just two headers on the API call itself.
 */
final class TelemetryTest extends TestCase
{
    public function testServiceCallSendsFingerprintHeaders(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->payments()->getPayment(1);

        $ua = $transport->header('User-Agent');
        $client = $transport->header('X-Unitpay-Client');

        $this->assertSame('unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION, $ua);

        $decoded = json_decode((string) $client, true);
        $this->assertSame(Unitpay::VERSION, $decoded['sdk_version']);
        $this->assertSame(Unitpay::API_VERSION, $decoded['api_version']);
        $this->assertSame('php', $decoded['lang']);
        $this->assertSame(PHP_VERSION, $decoded['lang_version']);
        $this->assertSame(PHP_OS_FAMILY, $decoded['platform']);
        $this->assertSame('unitpay', $decoded['publisher']);
    }

    /** The IP-feed fetch is a plain GET: no fingerprint headers ride along with it. */
    public function testIpFeedFetchDoesNotCarryFingerprintHeaders(): void
    {
        $transport = new FakeTransport('{"webhooks":["203.0.113.7"]}');
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->webhook()->refreshAllowedIps();

        $this->assertNull($transport->header('User-Agent'));
        $this->assertNull($transport->header('X-Unitpay-Client'));
    }
}
