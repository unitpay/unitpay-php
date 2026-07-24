<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Advance-offset receipt (offsetAdvance): creates a fiscal receipt for an earlier prepayment.
 * WARNING: the call creates a receipt, it is not a read-only reference method. Account-level API:
 * authenticates with the ACCOUNT key + login, passed explicitly.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

try {
    $response = $unitpay->api('offsetAdvance', $account + ['paymentId' => 3403575]);
    var_dump($response->result ?? $response->error ?? $response);
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
