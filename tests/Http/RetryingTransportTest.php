<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Tests\Support\SleeplessRetryingTransport;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\Response;
use Unitpay\Http\RetryingTransport;

/**
 * The retry decision table.
 *
 * The Unitpay API accepts no idempotency key, so a repeated initPayment can create a
 * second payment. That makes the policy the risky part of this feature, not the loop:
 * a retry is allowed only when the request is *provably* never to have reached the
 * server. Everything else — a read timeout, a 5xx, a 409, a 429 — may already have been
 * processed and must be surfaced to the caller instead.
 */
final class RetryingTransportTest extends TestCase
{
    /** A connect-phase failure never reached Unitpay, so repeating it is free. */
    public function testRetriesWhenTheRequestWasNeverSent(): void
    {
        $inner = new FakeTransport(
            Response::failed(7, 'Connection refused', false),
            Response::failed(7, 'Connection refused', false),
            Response::received(200, '{"result":{}}')
        );
        $transport = new SleeplessRetryingTransport($inner, 2);

        $response = $transport->request('https://unitpay.test/api?method=getPayment');

        $this->assertSame(3, $inner->callCount());
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * The single most important case in this file. A read timeout carries the same cURL
     * errno as a connect timeout, but the server saw the request — retrying it is how you
     * charge a customer twice.
     */
    public function testNeverRetriesAfterTheRequestReachedTheServer(): void
    {
        $inner = new FakeTransport(
            Response::failed(28, 'Operation timed out after 10001 milliseconds', true),
            Response::received(200, '{"result":{}}')
        );
        $transport = new SleeplessRetryingTransport($inner, 3);

        $response = $transport->request('https://unitpay.test/api?method=initPayment');

        $this->assertSame(1, $inner->callCount());
        $this->assertSame(0, $response->getStatusCode());
        $this->assertTrue($response->wasRequestSent());
        $this->assertSame([], $transport->sleeps());
    }

    /**
     * A status means the server answered, so it processed the request far enough to
     * decide. None of these may be repeated blindly — not even a 429, which other SDKs
     * retry only because they can send an idempotency key with it.
     *
     * @dataProvider serverAnsweredStatuses
     */
    public function testNeverRetriesAnyStatusTheServerReturned(int $status): void
    {
        $inner = new FakeTransport(
            Response::received($status, 'body'),
            Response::received(200, '{"result":{}}')
        );
        $transport = new SleeplessRetryingTransport($inner, 3);

        $response = $transport->request('https://unitpay.test/api?method=initPayment');

        $this->assertSame(1, $inner->callCount());
        $this->assertSame($status, $response->getStatusCode());
    }

    /** @return array<string, array{int}> */
    public function serverAnsweredStatuses(): array
    {
        return [
            'status 409' => [409],
            'status 429' => [429],
            'status 500' => [500],
            'status 502' => [502],
            'status 503' => [503],
        ];
    }

    /**
     * A transport that cannot classify its own failure must never be retried.
     *
     * The file_get_contents fallback is the case that matters: it sees no connect/read
     * phase, so a failure there may be a request Unitpay already processed. Retrying it
     * is how a fallback install charges a customer twice, and docs/getting-started.md
     * states outright that this path is never retried. `false` for "unknown" is not the
     * same claim as `false` for "provably never sent" — only the latter may be retried.
     *
     * @dataProvider unclassifiableFailures
     */
    public function testNeverRetriesAFailureTheTransportCouldNotClassify(Response $failure): void
    {
        $inner = new FakeTransport($failure, Response::received(200, '{"result":{}}'));
        $transport = new SleeplessRetryingTransport($inner, 3);

        $transport->request('https://unitpay.test/api?method=initPayment');

        $this->assertSame(1, $inner->callCount());
        $this->assertSame([], $transport->sleeps());
    }

    /** @return array<string, array{Response}> */
    public function unclassifiableFailures(): array
    {
        return [
            'stream fallback failure' => [
                Response::failed(Response::ERRNO_LOCAL, 'the fallback cannot report why', false),
            ],
            'allow_url_fopen disabled' => [
                Response::failed(Response::ERRNO_LOCAL, 'ext-curl missing and allow_url_fopen off', false),
            ],
        ];
    }

    public function testDoesNotRetryASuccess(): void
    {
        $inner = new FakeTransport('{"result":{}}');
        $transport = new SleeplessRetryingTransport($inner, 3);

        $transport->request('https://unitpay.test/api?method=getPayment');

        $this->assertSame(1, $inner->callCount());
    }

    public function testStopsAtTheConfiguredLimitAndReturnsTheLastFailure(): void
    {
        $inner = new FakeTransport(Response::failed(6, 'Could not resolve host', false));
        $transport = new SleeplessRetryingTransport($inner, 2);

        $response = $transport->request('https://unitpay.test/api?method=getPayment');

        // 1 initial attempt + 2 retries.
        $this->assertSame(3, $inner->callCount());
        $this->assertSame(6, $response->getErrno());
        $this->assertCount(2, $transport->sleeps());
    }

    public function testZeroRetriesPerformsExactlyOneCall(): void
    {
        $inner = new FakeTransport(Response::failed(7, 'Connection refused', false));
        $transport = new SleeplessRetryingTransport($inner, 0);

        $transport->request('https://unitpay.test/api?method=getPayment');

        $this->assertSame(1, $inner->callCount());
        $this->assertSame([], $transport->sleeps());
    }

    /**
     * A retry must re-send the same bytes. If the URL or the headers were rebuilt between
     * attempts, a signed request could be re-signed differently — or the fluent-setter
     * params could be dropped, which is why retries live below the service layer at all.
     */
    public function testEveryAttemptSendsIdenticalUrlAndHeaders(): void
    {
        $inner = new FakeTransport(Response::failed(7, 'Connection refused', false));
        $transport = new SleeplessRetryingTransport($inner, 2);
        $url = 'https://unitpay.test/api?method=initPayment&sum=100&signature=abc';
        $headers = ['User-Agent: unitpay-php-sdk/4.0.0', 'Unitpay-Client: {"lang":"php"}'];

        $transport->request($url, $headers);

        $this->assertSame(3, $inner->callCount());
        $this->assertSame($url, $inner->url(0));
        $this->assertSame($url, $inner->url(1));
        $this->assertSame($url, $inner->url(2));
        foreach ([0, 1, 2] as $attempt) {
            $this->assertSame('unitpay-php-sdk/4.0.0', $inner->header('User-Agent', $attempt));
            $this->assertSame('{"lang":"php"}', $inner->header('Unitpay-Client', $attempt));
        }
    }

    /** Backoff grows and stays under the cap, so a retry storm cannot stall a request thread. */
    public function testBackoffIsExponentialAndCapped(): void
    {
        $inner = new FakeTransport(Response::failed(7, 'Connection refused', false));
        $transport = new SleeplessRetryingTransport($inner, 4, 0.5, 2.0);

        $transport->request('https://unitpay.test/api?method=getPayment');

        $sleeps = $transport->sleeps();
        $this->assertCount(4, $sleeps);
        foreach ($sleeps as $delay) {
            $this->assertGreaterThanOrEqual(0.25, $delay);
            $this->assertLessThanOrEqual(2.0, $delay);
        }
    }

    /**
     * Retries must not be invisible: a caller staring at a network error needs to know the
     * SDK already tried three times before giving up.
     */
    public function testTheReturnedFailureReportsHowManyAttemptsWereMade(): void
    {
        $inner = new FakeTransport(Response::failed(7, 'Connection refused', false));
        $transport = new SleeplessRetryingTransport($inner, 2);

        $response = $transport->request('https://unitpay.test/api?method=getPayment');

        $this->assertStringContainsString('Connection refused', $response->getTransportError());
        $this->assertStringContainsString('3 attempts', $response->getTransportError());
    }

    /** A single attempt is not "1 attempts", and it is not a retry story worth telling. */
    public function testASingleAttemptDoesNotClaimToHaveRetried(): void
    {
        $inner = new FakeTransport(Response::failed(7, 'Connection refused', false));
        $transport = new SleeplessRetryingTransport($inner, 0);

        $response = $transport->request('https://unitpay.test/api?method=getPayment');

        $this->assertSame('Connection refused', $response->getTransportError());
    }

    /**
     * @dataProvider invalidConfigurations
     */
    public function testInvalidConfigurationIsRejectedAtConstruction(
        int $maxRetries,
        float $baseDelay,
        float $maxDelay,
        string $expectedMessage
    ): void {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        new RetryingTransport(new FakeTransport(), $maxRetries, $baseDelay, $maxDelay);
    }

    /** @return array<string, array{int, float, float, string}> */
    public function invalidConfigurations(): array
    {
        return [
            'negative retries' => [-1, 0.5, 2.0, 'Max retries must not be negative'],
            'negative base delay' => [2, -0.5, 2.0, 'Base delay must not be negative'],
            'max delay below base' => [2, 2.0, 0.5, 'Max delay must not be smaller than the base delay'],
        ];
    }
}
