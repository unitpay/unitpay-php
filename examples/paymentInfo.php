<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payment info
 *
 * @link https://help.unitpay.ru/payments/payment-info
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

try {
    $response = $unitpay->payments()->getPayment(3403575);

    if (isset($response->result)) {
        var_dump($response->result);
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
