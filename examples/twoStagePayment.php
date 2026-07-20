<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Двухстадийные платежи: confirm (списание) или cancel (разблокировка)
 * заблокированных средств. Внимание: confirmPayment/cancelPayment возвращают
 * `message` на верхнем уровне, а не `result->message`.
 *
 * @link https://help.unitpay.ru/api/confirm-payment
 * @link https://help.unitpay.ru/api/cancel-payment
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$paymentId = 3403575;

// Списываем заблокированные средства.
$response = $unitpay->api('confirmPayment', ['paymentId' => $paymentId]);

// ...или разблокируем без списания.
// $response = $unitpay->api('cancelPayment', ['paymentId' => $paymentId]);

if (isset($response->message)) {
    print $response->message;
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
