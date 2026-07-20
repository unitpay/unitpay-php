<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Информация о платеже
 *
 * @link https://help.unitpay.ru/payments/payment-info
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$response = $unitpay->api('getPayment', [
    'paymentId' => 3403575
]);

if (isset($response->result)) {
    $paymentInfo = $response->result;
    var_dump($paymentInfo);
} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
