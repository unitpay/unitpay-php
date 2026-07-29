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

    public function testModuleAndStackReachBothHeaders(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-woocommerce', '2.1')
            ->setStack(['WordPress' => '6.5', 'WooCommerce' => '8.2']);

        $unitpay->payments()->getPayment(1);

        // Outermost host first, the module last as the narrowest context.
        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION
                . ' WordPress/6.5 WooCommerce/8.2 unitpay-woocommerce/2.1',
            $transport->header('User-Agent')
        );

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['name' => 'unitpay-woocommerce', 'version' => '2.1'], $decoded['module']);
        $this->assertSame(
            [
                ['name' => 'WordPress', 'version' => '6.5'],
                ['name' => 'WooCommerce', 'version' => '8.2'],
            ],
            $decoded['stack']
        );
    }

    /**
     * The four-layer stack the three fixed slots could not express: a solution on top of a
     * CMS, with the payment module on top of that.
     */
    public function testAFourLayerStackIsExpressible(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-bitrix', '3.1')
            ->setStack(['Bitrix' => '22.0', 'Aspro Optimus' => '1.8.2']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(
            ['Bitrix', 'Aspro Optimus'],
            array_column($decoded['stack'], 'name')
        );
        $this->assertSame('unitpay-bitrix', $decoded['module']['name']);
    }

    /** Idempotent by design, so a bootstrap that runs twice cannot double the stack. */
    public function testSetStackReplacesRatherThanAppends(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(['WordPress' => '6.5']);
        $unitpay->setStack(['Bitrix' => '22.0']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame([['name' => 'Bitrix', 'version' => '22.0']], $decoded['stack']);
    }

    /** A list was passed where a map was meant; "0" is not a product name. */
    public function testANumericKeyIsDropped(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(['WordPress', 'Bitrix' => '22.0']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame([['name' => 'Bitrix', 'version' => '22.0']], $decoded['stack']);
    }

    /** A list bounds the header where three fixed slots used to bound it for free. */
    public function testTheStackIsCappedAtEightEntries(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $stack = [];
        for ($i = 1; $i <= 12; $i++) {
            $stack['Product' . $i] = '1.0';
        }
        $unitpay->setStack($stack);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertCount(8, $decoded['stack']);
        $this->assertSame('Product8', $decoded['stack'][7]['name']);
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

    /**
     * An empty stack must not appear at all, rather than appear as `[]` — an integration with
     * nothing under it, such as a bare script or a Telegram bot, is an ordinary case.
     */
    public function testAnEmptyStackIsOmitted(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-telegram-bot-template', '1.0');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayHasKey('module', $decoded);
        $this->assertArrayNotHasKey('stack', $decoded);
    }

    /** And the same the other way round: a stack with no module named. */
    public function testAnUnsetModuleIsOmitted(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(['Laravel' => '11.0']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayHasKey('stack', $decoded);
        $this->assertArrayNotHasKey('module', $decoded);
    }

    /**
     * The facade caches service objects on first use, so a value set afterwards has to
     * reach the service that already exists — which only works because ClientInfo is
     * shared by reference rather than copied into the service at construction.
     */
    public function testAValueSetAfterAServiceWasBuiltStillTakesEffect(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->payments()->getPayment(1);
        $unitpay->setStack(['Bitrix' => '22.0']);
        $unitpay->payments()->getPayment(2);

        $this->assertStringNotContainsString('Bitrix/22.0', (string) $transport->header('User-Agent', 0));
        $this->assertStringContainsString('Bitrix/22.0', (string) $transport->header('User-Agent', 1));
    }

    public function testDisableTelemetryDropsTheClientHeaderButKeepsTheSdkVersion(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(['Bitrix' => '22.0'])->disableTelemetry();

        $unitpay->payments()->getPayment(1);

        $this->assertNull($transport->header('Unitpay-Client'));
        $this->assertSame('unitpay-php-sdk/' . Unitpay::VERSION, $transport->header('User-Agent'));
    }

    /**
     * A blank half would emit a meaningless "Bitrix/" or "/22.0" token, so the value is
     * dropped — but dropped silently. These setters run in an integration's bootstrap, and
     * a CMS that stops exposing its version string must cost a field in a header, not a
     * checkout.
     *
     * @dataProvider incompleteValues
     */
    public function testAnIncompleteModuleIsIgnoredRatherThanRejected(string $name, string $version): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);

        $unitpay->setModule($name, $version);
        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayNotHasKey('module', $decoded);
        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
    }

    /** @return array<string, array{string, string}> */
    public function incompleteValues(): array
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
     * Ignoring a blank value means leaving it alone, not clearing it: a setter that starts
     * coming back empty costs the update, not the value already reported.
     */
    public function testABlankOverwriteLeavesTheEarlierValueInPlace(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule('unitpay-bitrix', '3.1');
        $unitpay->setModule('unitpay-bitrix', '');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['name' => 'unitpay-bitrix', 'version' => '3.1'], $decoded['module']);
    }

    /** A value that is nothing but control characters has no usable half left. */
    public function testAValueOfOnlyControlCharactersIsDropped(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setModule("\r\n\t", '3.1');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertArrayNotHasKey('module', $decoded);
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
        $unitpay->setModule(str_repeat('中', 30), '1.0');

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(str_repeat('中', 21), $decoded['module']['name']);
        $this->assertSame(1, preg_match('//u', $decoded['module']['name']));
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
        $unitpay->setModule("\xC1\xE8\xF2\xF0\xE8\xEA\xF1", '22.0');

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertNotSame('', $client);

        $decoded = json_decode($client, true);
        $this->assertSame(Unitpay::VERSION, $decoded['sdk_version']);
        $this->assertSame(PHP_VERSION, $decoded['lang_version']);
        $this->assertArrayHasKey('module', $decoded);

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
        $unitpay->setModule('1С-Битрикс', '22.0');

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $client));

        $decoded = json_decode($client, true);
        $this->assertSame(['name' => '1С-Битрикс', 'version' => '22.0'], $decoded['module']);

        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
    }

    /**
     * Everything above is the module path. The stack is a second, unbounded input surface, so
     * each guarantee has to hold per entry — and one bad entry must not take its neighbours
     * with it.
     */
    public function testControlCharactersInAStackEntryCannotAddAHeaderLine(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(["evil\r\nX-Injected: 1" => '1.0']);

        $unitpay->payments()->getPayment(1);

        $ua = (string) $transport->header('User-Agent');
        $this->assertStringNotContainsString("\r", $ua);
        $this->assertStringNotContainsString("\n", $ua);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame('evilX-Injected: 1', $decoded['stack'][0]['name']);
    }

    public function testAStackEntryOfOnlyControlCharactersIsDroppedWithoutItsNeighbours(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack([
            'WordPress'  => '6.5',
            "\r\n\t"     => '1.0',
            'WooCommerce' => '8.2',
        ]);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['WordPress', 'WooCommerce'], array_column($decoded['stack'], 'name'));
    }

    public function testABlankVersionDropsOnlyItsOwnStackEntry(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        // Exactly what a CMS that stops exposing its version hands over.
        $unitpay->setStack(['WordPress' => '6.5', 'WooCommerce' => '', 'Bitrix' => '22.0']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(['WordPress', 'Bitrix'], array_column($decoded['stack'], 'name'));
    }

    public function testAnOverlongStackEntryIsTruncatedOnACharacterBoundary(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack([str_repeat('中', 30) => '1.0']);

        $unitpay->payments()->getPayment(1);

        $decoded = json_decode((string) $transport->header('Unitpay-Client'), true);
        $this->assertSame(str_repeat('中', 21), $decoded['stack'][0]['name']);
        $this->assertSame(1, preg_match('//u', $decoded['stack'][0]['name']));
    }

    public function testAnInvalidlyEncodedStackEntryCannotBlankTheHeader(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        // "Битрикс" in windows-1251: not valid UTF-8.
        $unitpay->setStack(["\xC1\xE8\xF2\xF0\xE8\xEA\xF1" => '22.0']);

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertNotSame('', $client);

        $decoded = json_decode($client, true);
        $this->assertSame(Unitpay::VERSION, $decoded['sdk_version']);
        $this->assertSame(PHP_VERSION, $decoded['lang_version']);
        $this->assertCount(1, $decoded['stack']);

        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION,
            $transport->header('User-Agent')
        );
    }

    public function testANonAsciiStackEntryRidesInTheJsonHeaderOnly(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', 'secret', $transport);
        $unitpay->setStack(['Bitrix' => '22.0', 'Аспро: Магазин' => '1.8.2']);

        $unitpay->payments()->getPayment(1);

        $client = (string) $transport->header('Unitpay-Client');
        $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $client));

        $decoded = json_decode($client, true);
        $this->assertSame(
            ['name' => 'Аспро: Магазин', 'version' => '1.8.2'],
            $decoded['stack'][1]
        );

        // The ASCII entry still makes the User-Agent; the other one is absent, not mangled.
        $this->assertSame(
            'unitpay-php-sdk/' . Unitpay::VERSION . ' api/' . Unitpay::API_VERSION . ' Bitrix/22.0',
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
