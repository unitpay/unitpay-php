<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Two-stage payments: confirm (capture) or cancel (release) held funds.
 * Note: confirmPayment/cancelPayment return `message` at the top level,
 * not `result->message`.
 *
 * @link https://help.unitpay.ru/api/confirm-payment
 * @link https://help.unitpay.ru/api/cancel-payment
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

$paymentId = 3403575;

try {
    // Capture the held funds.
    $response = $unitpay->payments()->confirmPayment($paymentId);

    // ...or release without capturing.
    // $response = $unitpay->payments()->cancelPayment($paymentId);

    if (isset($response->message)) {
        print $response->message;
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
