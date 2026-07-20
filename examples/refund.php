<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Возврат платежа (полный или частичный)
 *
 * @link https://help.unitpay.ru/api/payment-refund
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$response = $unitpay->api('refundPayment', [
    'paymentId' => 3403575,
    // 'sum' => 100, // необязательно: частичный возврат; для полного не указывайте
]);

if (isset($response->result->message)) {
    print $response->result->message;
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
