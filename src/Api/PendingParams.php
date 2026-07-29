<?php

namespace Unitpay\Api;

/**
 * Mutable holder for the fluent-setter params (cashItems, customerEmail, backUrl,
 * customerPhone) accumulated on the facade. Shared between the facade and its services.
 *
 * Only form(), initPayment() and offsetAdvance() read and drain them; every other call
 * leaves them untouched. A consuming call clears them, so a reused instance never carries
 * one order's receipt into the next.
 */
final class PendingParams
{
    /** @var array<string, mixed> */
    private array $params = [];

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Returns the accumulated params and clears them.
     * @return array<string, mixed>
     */
    public function drain(): array
    {
        $params = $this->params;
        $this->params = [];
        return $params;
    }
}
