<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Two-stage payments: confirm (capture) or cancel (release) held funds.
 * Note: confirmPayment/cancelPayment return a top-level `message`,
 * not `result->message`.
 *
 * @link https://help.unitpay.ru/api/confirm-payment
 * @link https://help.unitpay.ru/api/cancel-payment
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$paymentId = 3403575;

// Capture the held funds:
$response = $unitpay->api('confirmPayment', ['paymentId' => $paymentId]);

// ...or release them without charging:
// $response = $unitpay->api('cancelPayment', ['paymentId' => $paymentId]);

if (isset($response->message)) {
    print $response->message;
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
