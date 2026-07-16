<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payouts (mass-payment). Account-level API: authenticates with the ACCOUNT key
 * + login, not the project key. The account key is passed explicitly in api()
 * params, overriding the project key from the constructor.
 *
 * @link https://help.unitpay.ru/api/create_payout
 * @link https://help.unitpay.ru/api/payout_info
 * @link https://help.unitpay.ru/api/poluchenie-spravochnika-bankov-uchastnikov-sbp-api
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

// SBP participant banks — memberId is required for SBP payouts:
$banks = $unitpay->api('getSbpBankList', $account);
var_dump($banks->result ?? $banks->error ?? $banks);

$transactionId = 'payout-1782'; // unique on your side

// Create a payout to an SBP recipient:
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

    // Later — check the payout status by your transactionId:
    $info = $unitpay->api('massPaymentStatus', $account + ['transactionId' => $transactionId]);
    var_dump($info->result ?? $info->error ?? $info);
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
