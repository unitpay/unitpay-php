<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payment info
 *
 * @link https://help.unitpay.ru/payments/payment-info
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$response = $unitpay->api('getPayment', [
    'paymentId' => 3403575
]);

// If need user redirect on Payment Gate
if (isset($response->result)) {
    // Payment Info
    $paymentInfo = $response->result;
    var_dump($paymentInfo);
    // If error during api request
} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
