<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Информация о платеже
 *
 * @link https://help.unitpay.ru/payments/payment-info
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

try {
    $response = $unitpay->api('getPayment', [
        'paymentId' => 3403575
    ]);

    if (isset($response->result)) {
        var_dump($response->result);
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
