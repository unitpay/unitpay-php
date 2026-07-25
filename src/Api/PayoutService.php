<?php

namespace Unitpay\Api;

/**
 * Mass-payout operations plus the SBP bank list and BIN lookup. All are
 * account-level: pass the account key in $options['secretKey'] and the account email
 * as $login. Method names mirror the Unitpay API method names.
 */
final class PayoutService extends AbstractService
{
    /**
     * Create a payout. For SBP pass $options['memberId'] from getSbpBankList().
     * @param int|string $transactionId
     * @param int|float|string $sum
     * @param array<string, mixed> $options
     */
    public function massPayment(string $login, $transactionId, $sum, string $purse, string $paymentType, array $options = []): object
    {
        return $this->request('massPayment', array_merge([
            'login'         => $login,
            'transactionId' => $transactionId,
            'sum'           => $sum,
            'purse'         => $purse,
            'paymentType'   => $paymentType,
        ], $options));
    }

    /**
     * @param int|string $transactionId
     * @param array<string, mixed> $options
     */
    public function massPaymentStatus(string $login, $transactionId, array $options = []): object
    {
        return $this->request('massPaymentStatus', array_merge([
            'login'         => $login,
            'transactionId' => $transactionId,
        ], $options));
    }

    /**
     * @param int|float|string $sum
     * @param array<string, mixed> $options
     */
    public function massPaymentAvailableAmount(string $login, $sum, string $purse, string $paymentType, array $options = []): object
    {
        return $this->request('massPaymentAvailableAmount', array_merge([
            'login'       => $login,
            'sum'         => $sum,
            'purse'       => $purse,
            'paymentType' => $paymentType,
        ], $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function massPaymentCommissions(string $login, array $options = []): object
    {
        return $this->request('massPaymentCommissions', array_merge(['login' => $login], $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getSbpBankList(string $login, array $options = []): object
    {
        return $this->request('getSbpBankList', array_merge(['login' => $login], $options));
    }

    /**
     * @param int|string $bin
     * @param array<string, mixed> $options
     */
    public function getBinInfo(string $login, $bin, array $options = []): object
    {
        return $this->request('getBinInfo', array_merge([
            'login' => $login,
            'bin'   => $bin,
        ], $options));
    }
}
