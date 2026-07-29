<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\CurlTransport;

/**
 * Construction and timeout configuration only — the request paths talk to the network and
 * are exercised by hand, not by this suite.
 */
final class CurlTransportTest extends TestCase
{
    /** The pre-4.0 hardcoded values stay the defaults, so an existing call site is unchanged. */
    public function testDefaultTimeoutsAreUnchangedFromBefore(): void
    {
        $transport = new CurlTransport();

        $this->assertSame(5, $transport->getConnectTimeout());
        $this->assertSame(10, $transport->getTimeout());
    }

    public function testTimeoutsAreConfigurable(): void
    {
        $transport = new CurlTransport(2, 30);

        $this->assertSame(2, $transport->getConnectTimeout());
        $this->assertSame(30, $transport->getTimeout());
    }

    /**
     * cURL reads 0 as "wait forever". In a payment flow that is a request which never
     * comes back, so it is rejected rather than passed through.
     *
     * @dataProvider nonPositiveTimeouts
     */
    public function testNonPositiveConnectTimeoutIsRejected(int $seconds): void
    {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('Connect timeout must be a positive number of seconds');

        new CurlTransport($seconds, 10);
    }

    /**
     * @dataProvider nonPositiveTimeouts
     */
    public function testNonPositiveTimeoutIsRejected(int $seconds): void
    {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('Timeout must be a positive number of seconds');

        new CurlTransport(5, $seconds);
    }

    /** @return array<string, array{int}> */
    public function nonPositiveTimeouts(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /** The message has to name the offending value, or the caller has to go looking. */
    public function testRejectionMessageNamesTheValue(): void
    {
        try {
            new CurlTransport(5, -3);
            $this->fail('expected a validation exception');
        } catch (UnitpayValidationException $e) {
            $this->assertStringContainsString('-3', $e->getMessage());
        }
    }

    /**
     * The stream path joins header lines with "\r\n", so a newline inside a value would add
     * a header line of the caller's choosing. Nothing the SDK builds can carry one — this
     * is the second line of defence, and it drops the line rather than raising, because a
     * transport must not abort a payment over a diagnostic header.
     */
    public function testHeaderLinesCarryingANewlineAreDropped(): void
    {
        $sanitized = CurlTransport::sanitizeHeaders([
            'User-Agent: unitpay-php-sdk/4.0.0',
            "Unitpay-Client: {\"lang\":\"php\"}\r\nX-Injected: 1",
            'Accept: application/json',
        ]);

        $this->assertSame(
            ['User-Agent: unitpay-php-sdk/4.0.0', 'Accept: application/json'],
            $sanitized
        );
    }

    public function testOrdinaryHeaderLinesPassThroughUnchanged(): void
    {
        $headers = ['User-Agent: unitpay-php-sdk/4.0.0', 'Unitpay-Client: {"lang":"php"}'];

        $this->assertSame($headers, CurlTransport::sanitizeHeaders($headers));
    }
}
