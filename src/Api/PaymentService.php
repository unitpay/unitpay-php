<?php

namespace Unitpay\Api;

/**
 * Payment operations: create, fetch, refund, two-stage confirm/cancel and the
 * advance-offset fiscal receipt. Method names mirror the Unitpay API method names.
 */
final class PaymentService extends AbstractService
{
    /**
     * Creates a payment. Consuming call: the params accumulated by setCashItems(),
     * setCustomerEmail(), setCustomerPhone() and setBackUrl() are folded in here and
     * cleared.
     *
     * @param int|float|string $sum
     * @param int|string $projectId
     * @param array<string, mixed> $options extra params (e.g. desc, currency, account)
     */
    public function initPayment(string $account, $sum, $projectId, string $paymentType, array $options = []): object
    {
        return $this->request('initPayment', $this->withPending(array_merge([
            'account'     => $account,
            'sum'         => $sum,
            'projectId'   => $projectId,
            'paymentType' => $paymentType,
        ], $options)));
    }

    /**
     * @param int|string $paymentId
     * @param array<string, mixed> $options
     */
    public function getPayment($paymentId, array $options = []): object
    {
        return $this->request('getPayment', array_merge(['paymentId' => $paymentId], $options));
    }

    /**
     * Refund a payment (full, or partial via $options['sum']).
     * @param int|string $paymentId
     * @param array<string, mixed> $options
     */
    public function refundPayment($paymentId, array $options = []): object
    {
        return $this->request('refundPayment', array_merge(['paymentId' => $paymentId], $options));
    }

    /**
     * Confirm (capture) a two-stage payment.
     * @param int|string $paymentId
     * @param array<string, mixed> $options
     */
    public function confirmPayment($paymentId, array $options = []): object
    {
        return $this->request('confirmPayment', array_merge(['paymentId' => $paymentId], $options));
    }

    /**
     * Cancel (release) a two-stage payment.
     * @param int|string $paymentId
     * @param array<string, mixed> $options
     */
    public function cancelPayment($paymentId, array $options = []): object
    {
        return $this->request('cancelPayment', array_merge(['paymentId' => $paymentId], $options));
    }

    /**
     * Advance-offset fiscal receipt. Account-level: pass the account key in
     * $options['secretKey'] and optionally cashItems. Consuming call: a receipt set via
     * setCashItems() is folded in here and cleared.
     * @param int|string $paymentId
     * @param array<string, mixed> $options
     */
    public function offsetAdvance(string $login, $paymentId, array $options = []): object
    {
        return $this->request('offsetAdvance', $this->withPending(array_merge([
            'login'     => $login,
            'paymentId' => $paymentId,
        ], $options)));
    }
}
