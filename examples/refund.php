<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payment refund (full or partial)
 *
 * @link https://help.unitpay.ru/api/payment-refund
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

try {
    // Omit the options for a full refund; pass ['sum' => 100] to refund part of it.
    $response = $unitpay->payments()->refundPayment(3403575);

    if (isset($response->result->message)) {
        print $response->result->message;
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
