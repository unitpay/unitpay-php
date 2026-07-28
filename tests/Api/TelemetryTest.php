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
        $client = $transport->header('Unitpay-Client');

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

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['name' => 'Laravel', 'version' => '11.0'], $decoded['framework']);
        $this->assertSame(['name' => 'Bitrix', 'version' => '22.0'], $decoded['cms']);
        $this->assertSame(['name' => 'unitpay-bitrix', 'version' => '3.1'], $decoded['module']);
    }

    /**
     * The reason the JSON header carries an object rather than a joined string: a Composer
     * package name contains the separator, so "unitpay/woocommerce/2.1" gives a consumer
     * three segments and one delimiter to guess between.
     */
    public function testANameContainingASlashStaysSeparable(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay/woocommerce', '2.1');

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertStringContainsString('"module":{"name":"unitpay/woocommerce"', $client);

        $decoded = json_decode($client, true);
        $this->assertSame(['name' => 'unitpay/woocommerce', 'version' => '2.1'], $decoded['module']);
    }

    /** An unset slot must not appear at all, rather than appear empty. */
    public function testUnsetSlotsAreOmitted(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-bitrix', '3.1');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
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

        $this->assertNull($transport->header('Unitpay-Client'));
        $this->assertSame('unitpay-php-sdk/' . Unitpay::VERSION, $transport->header('User-Agent'));
    }

    /**
     * A blank half would emit a meaningless "Bitrix/" or "/22.0" token, so the slot is
     * dropped — but dropped silently. These setters run in an integration's bootstrap, and
     * a CMS that stops exposing its version string must cost a field in a header, not a
     * checkout.
     *
     * @dataProvider incompleteSlots
     */
    public function testAnIncompleteSlotIsIgnoredRatherThanRejected(string $name, string $version): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->setCms($name, $version);
        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayNotHasKey('cms', $decoded);
        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
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

    /**
     * trim() only cleans the edges, so a CR/LF in the middle of a value used to reach the
     * transport — which joins header lines with "\r\n" — and add a header line of the
     * caller's choosing. Slot values often come from a module's settings screen.
     */
    public function testControlCharactersCannotAddAHeaderLine(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule("evil\r\nX-Injected: 1", '1.0');

        $unitpay->payments()->getPayment(1);

        $ua = (string) $transport->header('User-Agent');
        $this->assertStringNotContainsString("\r", $ua);
        $this->assertStringNotContainsString("\n", $ua);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame('evilX-Injected: 1', $decoded['module']['name']);
    }

    /**
     * Ignoring a blank value means leaving the slot alone, not clearing it: a setter that
     * starts coming back empty costs the update, not the value already reported.
     */
    public function testABlankOverwriteLeavesTheEarlierValueInPlace(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setCms('Bitrix', '22.0');
        $unitpay->setCms('Bitrix', '');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['name' => 'Bitrix', 'version' => '22.0'], $decoded['cms']);
    }

    /** A value that is nothing but control characters has no usable half left. */
    public function testAValueOfOnlyControlCharactersDropsTheSlot(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setCms("\r\n\t", '22.0');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayNotHasKey('cms', $decoded);
    }

    /**
     * The cap counts bytes, so it can land inside a multi-byte character. 30 three-byte
     * characters are 90 bytes; cutting at 64 leaves one stray byte, which must be dropped
     * rather than shipped as a broken sequence.
     */
    public function testAnOverlongValueIsTruncatedOnACharacterBoundary(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setCms(str_repeat('中', 30), '1.0');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(str_repeat('中', 21), $decoded['cms']['name']);
        $this->assertSame(1, preg_match('//u', $decoded['cms']['name']));
    }

    /**
     * json_encode returns false on invalid UTF-8, and the old `(string)` cast turned that
     * into an empty header — so one legacy windows-1251 CMS name cost the entire payload,
     * sdk_version and lang_version included.
     */
    public function testAnInvalidlyEncodedNameCannotBlankTheHeader(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        // "Битрикс" as a legacy windows-1251 install would hand it over: not valid UTF-8.
        $unitpay->setCms("\xC1\xE8\xF2\xF0\xE8\xEA\xF1", '22.0');

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertNotSame('', $client);

        $decoded = json_decode($client, true);
        $this->assertSame(Unitpay::VERSION, $decoded['sdk_version']);
        $this->assertSame(PHP_VERSION, $decoded['lang_version']);
        $this->assertArrayHasKey('cms', $decoded);

        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
    }

    /**
     * A User-Agent is an ASCII field. The JSON header keeps the name losslessly through
     * \uXXXX escaping, which is also what keeps that header value itself ASCII.
     */
    public function testANonAsciiNameRidesInTheJsonHeaderOnly(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setCms('1С-Битрикс', '22.0');

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $client));

        $decoded = json_decode($client, true);
        $this->assertSame(['name' => '1С-Битрикс', 'version' => '22.0'], $decoded['cms']);

        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
    }

    /** The IP-feed fetch is a plain GET: no fingerprint headers ride along with it. */
    public function testIpFeedFetchDoesNotCarryFingerprintHeaders(): void
    {
        $transport = new FakeTransport('{"webhooks":["203.0.113.7"]}');
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->webhook()->refreshAllowedIps();

        $this->assertNull($transport->header('User-Agent'));
        $this->assertNull($transport->header('Unitpay-Client'));
    }
}
