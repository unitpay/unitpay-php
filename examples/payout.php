<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Выплаты (mass-payment). API уровня аккаунта: аутентифицируется ключом АККАУНТА +
 * login, а не ключом проекта. Ключ аккаунта передаётся явно в параметрах api() и
 * переопределяет ключ проекта из конструктора.
 *
 * @link https://help.unitpay.ru/api/create_payout
 * @link https://help.unitpay.ru/api/payout_info
 * @link https://help.unitpay.ru/api/poluchenie-spravochnika-bankov-uchastnikov-sbp-api
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

// Банки — участники СБП: memberId обязателен для выплат по СБП.
$banks = $unitpay->api('getSbpBankList', $account);
var_dump($banks->result ?? $banks->error ?? $banks);

$transactionId = 'payout-1782'; // уникальный на вашей стороне

// Создаём выплату получателю по СБП.
$response = $unitpay->api('massPayment', $account + [
    'transactionId' => $transactionId,
    'sum'           => 100,
    'purse'         => '79510000071',
    'paymentType'   => 'sbp',
    'memberId'      => '100000000004', // из getSbpBankList; только для СБП
]);

if (isset($response->result)) {
    $payoutId = $response->result->payoutId;
    $status = $response->result->status; // success | not_completed

    // Позже — проверяем статус выплаты по вашему transactionId.
    $info = $unitpay->api('massPaymentStatus', $account + ['transactionId' => $transactionId]);
    var_dump($info->result ?? $info->error ?? $info);
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
