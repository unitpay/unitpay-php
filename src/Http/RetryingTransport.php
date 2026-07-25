<?php

namespace Unitpay\Http;

use Unitpay\Exception\UnitpayValidationException;

/**
 * Wraps another transport and repeats an attempt that never reached Unitpay.
 *
 * The policy is deliberately far narrower than the one other payment SDKs ship. They
 * retry timeouts, 409s and 5xx because they can attach an idempotency key that makes a
 * repeat harmless; the Unitpay API accepts no such key, so here a repeat of a delivered
 * initPayment can create a second payment. Only a failure that provably never left the
 * client is retried: DNS failure, refused connection, connect-phase timeout.
 *
 * Everything the server answered — any status at all — and every failure that happened
 * after the connection was established is returned to the caller untouched.
 *
 * Not final: sleep() is a test seam, matching how WebhookVerifier exposes getIp() and
 * isAllowedIp().
 */
class RetryingTransport implements TransportInterface
{
    private TransportInterface $inner;
    private int $maxRetries;
    private float $baseDelay;
    private float $maxDelay;

    /**
     * @param int   $maxRetries retries *after* the first attempt, so the default of 2
     *                          means at most 3 calls
     * @param float $baseDelay  seconds before the first retry, doubled on each subsequent one
     * @param float $maxDelay   ceiling for the doubling, in seconds
     *
     * @throws UnitpayValidationException on a negative count/delay or an inverted range
     */
    public function __construct(
        TransportInterface $inner,
        int $maxRetries = 2,
        float $baseDelay = 0.5,
        float $maxDelay = 2.0
    ) {
        if ($maxRetries < 0) {
            throw new UnitpayValidationException(
                sprintf('Max retries must not be negative, got %d.', $maxRetries)
            );
        }
        if ($baseDelay < 0) {
            throw new UnitpayValidationException(
                sprintf('Base delay must not be negative, got %s.', $baseDelay)
            );
        }
        if ($maxDelay < $baseDelay) {
            throw new UnitpayValidationException(
                sprintf(
                    'Max delay must not be smaller than the base delay, got %s < %s.',
                    $maxDelay,
                    $baseDelay
                )
            );
        }

        $this->inner = $inner;
        $this->maxRetries = $maxRetries;
        $this->baseDelay = $baseDelay;
        $this->maxDelay = $maxDelay;
    }

    /** The wrapped transport, for callers that need to reach it without the retry policy. */
    public function getInner(): TransportInterface
    {
        return $this->inner;
    }

    /**
     * @param string[] $headers
     */
    public function request(string $url, array $headers = []): Response
    {
        $attempts = 0;

        do {
            if ($attempts > 0) {
                $this->sleep($this->backoff($attempts));
            }
            $response = $this->inner->request($url, $headers);
            ++$attempts;
        } while ($this->shouldRetry($response) && $attempts <= $this->maxRetries);

        return $this->describeAttempts($response, $attempts);
    }

    /**
     * Pauses between attempts. Overridden in tests so the suite never actually waits.
     */
    protected function sleep(float $seconds): void
    {
        usleep((int) round($seconds * 1000000));
    }

    /**
     * Retry only on positive knowledge that the request never left this client.
     *
     * Three conditions, each excluding a way the server may already have acted:
     *  - a status means it answered;
     *  - wasRequestSent() means it saw the request even though no status came back;
     *  - ERRNO_LOCAL means the transport could not classify the failure at all, and
     *    "unknown" is not "not sent". The file_get_contents fallback reports exactly this:
     *    it sees no connect/read phase, so a failure there may be a delivered request.
     *
     * The last condition is the one that is easy to lose. Dropping it makes a fallback
     * install repeat a delivered initPayment, and the API accepts no idempotency key to
     * make that harmless. Do not widen this to cover 5xx or 429 either, for the same reason.
     */
    private function shouldRetry(Response $response): bool
    {
        return $response->getStatusCode() === 0
            && !$response->wasRequestSent()
            && $response->getErrno() !== Response::ERRNO_LOCAL;
    }

    /**
     * Exponential backoff, capped, with jitter over the upper half of the interval so a
     * fleet of clients failing at the same moment does not retry in lockstep.
     */
    private function backoff(int $retry): float
    {
        $delay = min($this->baseDelay * (2 ** ($retry - 1)), $this->maxDelay);
        $jittered = $delay * 0.5 * (1 + (mt_rand() / mt_getrandmax()));

        return max($this->baseDelay * 0.5, $jittered);
    }

    /**
     * Records the attempt count in the failure the caller receives. Without it a retried
     * failure is indistinguishable from a single one, and the seconds spent retrying look
     * like an unexplained stall.
     */
    private function describeAttempts(Response $response, int $attempts): Response
    {
        if ($attempts < 2 || $response->getStatusCode() !== 0) {
            return $response;
        }

        return Response::failed(
            $response->getErrno(),
            sprintf('%s (after %d attempts)', $response->getTransportError(), $attempts),
            $response->wasRequestSent()
        );
    }
}
