<?php

namespace Tests\Support;

use Unitpay\Http\RetryingTransport;

/**
 * RetryingTransport with the backoff replaced by a recorder, so the retry policy can be
 * exercised at full speed and the computed delays can be asserted on.
 */
final class SleeplessRetryingTransport extends RetryingTransport
{
    /** @var float[] */
    private array $sleeps = [];

    /** @return float[] the delay of every backoff that would have been slept, in order */
    public function sleeps(): array
    {
        return $this->sleeps;
    }

    protected function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}
