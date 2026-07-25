<?php

namespace Tests\Support;

use Unitpay\Webhook\WebhookVerifier;

/**
 * WebhookVerifier with a clock the test controls, so the replay window can be probed at
 * its exact boundary without sleeping and without depending on how long the assertion
 * itself takes to run.
 */
final class FrozenClockWebhookVerifier extends WebhookVerifier
{
    private int $now = 0;

    public function freezeAt(int $timestamp): self
    {
        $this->now = $timestamp;

        return $this;
    }

    protected function now(): int
    {
        return $this->now;
    }
}
