<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payouts (mass-payment). Account-level API: authenticates with the ACCOUNT key +
 * login, not the project key. The login is the first argument of every payout method,
 * and the account key goes in the options array, overriding the project key from the
 * constructor.
 *
 * @link https://help.unitpay.ru/api/create_payout
 * @link https://help.unitpay.ru/api/payout_info
 * @link https://help.unitpay.ru/api/poluchenie-spravochnika-bankov-uchastnikov-sbp-api
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Model\Enum\PaymentType;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

$accountKey = ['secretKey' => $accountSecretKey];
$transactionId = 'payout-1782'; // unique on your side

try {
    $payouts = $unitpay->payouts();

    // SBP member banks: memberId is required for SBP payouts.
    $banks = $payouts->getSbpBankList($login, $accountKey);
    var_dump($banks->result ?? $banks->error ?? $banks);

    // Create a payout to the recipient via SBP.
    $response = $payouts->massPayment(
        $login,
        $transactionId,
        100,
        '79510000071',
        PaymentType::SBP,
        $accountKey + ['memberId' => '100000000004'] // memberId from getSbpBankList; SBP only
    );

    if (isset($response->result)) {
        $payoutId = $response->result->payoutId;
        $status = $response->result->status; // success | not_completed

        // Later — check the payout status by your transactionId.
        $info = $payouts->massPaymentStatus($login, $transactionId, $accountKey);
        var_dump($info->result ?? $info->error ?? $info);
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
