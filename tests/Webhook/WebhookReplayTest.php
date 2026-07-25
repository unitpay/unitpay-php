<?php

namespace Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Tests\Support\FrozenClockWebhookVerifier;
use Unitpay\Exception\UnitpayReplayException;
use Unitpay\Exception\UnitpaySignatureException;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Webhook\WebhookVerifier;

/**
 * The replay window.
 *
 * A signature alone says a webhook was genuine once, not that it is genuine now: a
 * captured request replays forever. `params[date]` is inside the signed payload, so its
 * timestamp cannot be forged, which is what makes a freshness check worth anything here.
 *
 * The clock is frozen rather than real, so the boundary can be probed exactly instead of
 * "within a second or so".
 */
final class WebhookReplayTest extends TestCase
{
    private const SECRET = 'secret';
    private const ALLOWED_IP = '31.186.100.49';
    private const NOW = 1785000000;

    /**
     * @param array<string, mixed> $overrides
     * @return array{method: string, params: array<string, mixed>}
     */
    private function requestAt(int $timestamp, string $method = 'pay', array $overrides = []): array
    {
        $params = array_merge([
            'account'   => '42',
            'orderSum'  => '100.00',
            'unitpayId' => '999',
            'date'      => WebhookVerifierTest::dateAt($timestamp),
        ], $overrides);

        $params['signature'] = (new SignatureBuilder())->build($params, self::SECRET, $method);

        return ['method' => $method, 'params' => $params];
    }

    /**
     * @param array{method: string, params: array<string, mixed>} $request
     */
    private function verifier(array $request): FrozenClockWebhookVerifier
    {
        $verifier = new FrozenClockWebhookVerifier(
            self::SECRET,
            new SignatureBuilder(),
            new FakeTransport(),
            'https://unitpay.ru/ips/ips_webhooks.json',
            $request,
            self::ALLOWED_IP
        );

        return $verifier->freezeAt(self::NOW);
    }

    public function testAFreshWebhookPasses(): void
    {
        $this->assertTrue($this->verifier($this->requestAt(self::NOW))->checkHandlerRequest());
    }

    public function testAWebhookFromTenMinutesAgoIsRejected(): void
    {
        $this->expectException(UnitpayReplayException::class);
        $this->expectExceptionMessage('outside the 300s tolerance window');

        $this->verifier($this->requestAt(self::NOW - 600))->checkHandlerRequest();
    }

    /** Clock skew cuts both ways: a timestamp from the future is just as untrustworthy. */
    public function testAWebhookFromTenMinutesInTheFutureIsRejected(): void
    {
        $this->expectException(UnitpayReplayException::class);

        $this->verifier($this->requestAt(self::NOW + 600))->checkHandlerRequest();
    }

    /**
     * @dataProvider boundaryOffsets
     */
    public function testTheBoundaryIsInclusive(int $offset): void
    {
        $this->assertTrue($this->verifier($this->requestAt(self::NOW + $offset))->checkHandlerRequest());
    }

    /** @return array<string, array{int}> */
    public function boundaryOffsets(): array
    {
        return [
            'exactly 300s old' => [-300],
            'exactly 300s ahead' => [300],
        ];
    }

    /**
     * @dataProvider justOutsideOffsets
     */
    public function testOneSecondPastTheBoundaryIsRejected(int $offset): void
    {
        $this->expectException(UnitpayReplayException::class);

        $this->verifier($this->requestAt(self::NOW + $offset))->checkHandlerRequest();
    }

    /** @return array<string, array{int}> */
    public function justOutsideOffsets(): array
    {
        return [
            '301s old' => [-301],
            '301s ahead' => [301],
        ];
    }

    public function testToleranceZeroDisablesTheCheck(): void
    {
        $verifier = $this->verifier($this->requestAt(self::NOW - 86400))->setWebhookTolerance(0);

        $this->assertTrue($verifier->checkHandlerRequest());
    }

    public function testToleranceIsConfigurable(): void
    {
        $verifier = $this->verifier($this->requestAt(self::NOW - 600))->setWebhookTolerance(900);

        $this->assertTrue($verifier->checkHandlerRequest());
    }

    public function testNegativeToleranceIsRejected(): void
    {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('Webhook tolerance must not be negative');

        $this->verifier($this->requestAt(self::NOW))->setWebhookTolerance(-1);
    }

    /**
     * A date the SDK cannot parse is a failed freshness check, not something to wave
     * through — otherwise garbage in the field would silently disable the window.
     *
     * @dataProvider malformedDates
     */
    public function testAMalformedDateIsRejected(string $date): void
    {
        $this->expectException(UnitpayReplayException::class);

        $this->verifier($this->requestAt(self::NOW, 'pay', ['date' => $date]))->checkHandlerRequest();
    }

    /** @return array<string, array{string}> */
    public function malformedDates(): array
    {
        return [
            'not a date' => ['not-a-date'],
            'impossible components' => ['2026-13-45 99:99:99'],
            'wrong format' => ['25/07/2026 12:00'],
            'empty' => [''],
        ];
    }

    /**
     * The regression this whole design exists to prevent. `strtotime()` would resolve the
     * same string against the ambient date.timezone, so a webhook accepted on a Moscow
     * server would be rejected on a UTC one — three hours is ten times the window.
     *
     * @dataProvider serverTimezones
     */
    public function testTheVerdictDoesNotDependOnTheServerTimezone(string $timezone): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $this->assertTrue($this->verifier($this->requestAt(self::NOW))->checkHandlerRequest());
        } finally {
            date_default_timezone_set($original);
        }
    }

    /** @return array<string, array{string}> */
    public function serverTimezones(): array
    {
        return [
            'UTC' => ['UTC'],
            'Europe/Moscow' => ['Europe/Moscow'],
            'America/New_York' => ['America/New_York'],
            'Asia/Tokyo' => ['Asia/Tokyo'],
        ];
    }

    /**
     * Ordering matters: the signature gate stays first, so an attacker cannot use an
     * unsigned payload to probe how far the server clock is off.
     */
    public function testABadSignatureIsReportedBeforeAStaleDate(): void
    {
        $request = $this->requestAt(self::NOW - 86400);
        $request['params']['orderSum'] = '0.01'; // invalidates the signature

        $this->expectException(UnitpaySignatureException::class);
        $this->expectExceptionMessage('Wrong signature');

        $this->verifier($request)->checkHandlerRequest();
    }

    /**
     * `date` is documented for all four inbound methods, but it is also part of the signed
     * payload — an attacker cannot strip it, because removing any param changes the hash
     * and the signature check fails first. So if it is ever genuinely absent, that is
     * Unitpay's choice, not an attack, and rejecting every such webhook would cost the
     * merchant real notifications for no security gain.
     */
    public function testAWebhookWithoutADateIsAcceptedRatherThanBlocked(): void
    {
        $params = ['account' => '42', 'orderSum' => '100.00', 'unitpayId' => '999'];
        $params['signature'] = (new SignatureBuilder())->build($params, self::SECRET, 'pay');

        $this->assertTrue($this->verifier(['method' => 'pay', 'params' => $params])->checkHandlerRequest());
    }

    /** The window applies to every inbound method, not only to `pay`. */
    public function testTheWindowAppliesToEveryInboundMethod(): void
    {
        foreach (['check', 'pay', 'preauth', 'error'] as $method) {
            $fresh = $this->verifier($this->requestAt(self::NOW, $method));
            $this->assertTrue($fresh->checkHandlerRequest(), $method . ' should pass when fresh');

            try {
                $this->verifier($this->requestAt(self::NOW - 3600, $method))->checkHandlerRequest();
                $this->fail($method . ' should be rejected when stale');
            } catch (UnitpayReplayException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Still catchable by handlers written against the pre-4.0 exception surface. */
    public function testReplayExceptionIsCaughtBySignatureExceptionHandlers(): void
    {
        try {
            $this->verifier($this->requestAt(self::NOW - 600))->checkHandlerRequest();
            $this->fail('expected a replay exception');
        } catch (UnitpaySignatureException $e) {
            $this->assertInstanceOf(UnitpayReplayException::class, $e);
            $this->assertInstanceOf(WebhookVerifier::class, $this->verifier($this->requestAt(self::NOW)));
        }
    }
}
