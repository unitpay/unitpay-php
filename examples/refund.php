<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payment refund (full or partial)
 *
 * @link https://help.unitpay.ru/api/payment-refund
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

try {
    $response = $unitpay->api('refundPayment', [
        'paymentId' => 3403575,
        // 'sum' => 100, // optional: partial refund; omit for a full refund
    ]);

    if (isset($response->result->message)) {
        print $response->result->message;
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
