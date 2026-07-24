<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payouts (mass-payment). Account-level API: authenticates with the ACCOUNT key +
 * login, not the project key. The account key is passed explicitly in the api()
 * parameters and overrides the project key from the constructor.
 *
 * @link https://help.unitpay.ru/api/create_payout
 * @link https://help.unitpay.ru/api/payout_info
 * @link https://help.unitpay.ru/api/poluchenie-spravochnika-bankov-uchastnikov-sbp-api
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];
$transactionId = 'payout-1782'; // unique on your side

try {
    // SBP member banks: memberId is required for SBP payouts.
    $banks = $unitpay->api('getSbpBankList', $account);
    var_dump($banks->result ?? $banks->error ?? $banks);

    // Create a payout to the recipient via SBP.
    $response = $unitpay->api('massPayment', $account + [
        'transactionId' => $transactionId,
        'sum'           => 100,
        'purse'         => '79510000071',
        'paymentType'   => 'sbp',
        'memberId'      => '100000000004', // from getSbpBankList; SBP only
    ]);

    if (isset($response->result)) {
        $payoutId = $response->result->payoutId;
        $status = $response->result->status; // success | not_completed

        // Later — check the payout status by your transactionId.
        $info = $unitpay->api('massPaymentStatus', $account + ['transactionId' => $transactionId]);
        var_dump($info->result ?? $info->error ?? $info);
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
