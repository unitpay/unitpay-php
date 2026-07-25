<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Exception\UnitpayValidationException;
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

    public function testIntegrationSlotsReachBothHeaders(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setFramework('Laravel', '11.0')
            ->setCms('Bitrix', '22.0')
            ->setModule('unitpay-bitrix', '3.1');

        $unitpay->payments()->getPayment(1);

        $ua = (string) $transport->header('User-Agent');
        $this->assertStringContainsString('Laravel/11.0', $ua);
        $this->assertStringContainsString('Bitrix/22.0', $ua);
        $this->assertStringContainsString('unitpay-bitrix/3.1', $ua);

        $decoded = json_decode((string) $transport->header('X-Unitpay-Client'), true);
        $this->assertSame('Laravel/11.0', $decoded['framework']);
        $this->assertSame('Bitrix/22.0', $decoded['cms']);
        $this->assertSame('unitpay-bitrix/3.1', $decoded['module']);
    }

    /** An unset slot must not appear at all, rather than appear empty. */
    public function testUnsetSlotsAreOmitted(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-bitrix', '3.1');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('X-Unitpay-Client'), true);
        $this->assertArrayHasKey('module', $decoded);
        $this->assertArrayNotHasKey('cms', $decoded);
        $this->assertArrayNotHasKey('framework', $decoded);
    }

    /**
     * The facade caches service objects on first use, so a slot set afterwards has to
     * reach the service that already exists — which only works because ClientInfo is
     * shared by reference rather than copied into the service at construction.
     */
    public function testASlotSetAfterAServiceWasBuiltStillTakesEffect(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->payments()->getPayment(1);
        $unitpay->setCms('Bitrix', '22.0');
        $unitpay->payments()->getPayment(2);

        $this->assertStringNotContainsString('Bitrix/22.0', (string) $transport->header('User-Agent', 0));
        $this->assertStringContainsString('Bitrix/22.0', (string) $transport->header('User-Agent', 1));
    }

    public function testDisableTelemetryDropsTheClientHeaderButKeepsTheSdkVersion(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setCms('Bitrix', '22.0')->disableTelemetry();

        $unitpay->payments()->getPayment(1);

        $this->assertNull($transport->header('X-Unitpay-Client'));
        $this->assertSame('unitpay-php-sdk/' . Unitpay::VERSION, $transport->header('User-Agent'));
    }

    /**
     * A blank half would emit a meaningless "Bitrix/" or "/22.0" token, which is worse
     * than sending nothing.
     *
     * @dataProvider incompleteSlots
     */
    public function testAnIncompleteSlotIsRejected(string $name, string $version): void
    {
        $unitpay = new Unitpay('unitpay.test', 'secret', new FakeTransport());

        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('needs both a name and a version');

        $unitpay->setCms($name, $version);
    }

    /** @return array<string, array{string, string}> */
    public function incompleteSlots(): array
    {
        return [
            'empty name' => ['', '22.0'],
            'empty version' => ['Bitrix', ''],
            'blank name' => ['   ', '22.0'],
        ];
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
