<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Advance-offset receipt (offsetAdvance): creates a fiscal receipt for an earlier prepayment.
 * WARNING: the call creates a receipt, it is not a read-only reference method. Account-level API:
 * authenticates with the ACCOUNT key + login, passed explicitly.
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

// The account key overrides the project key from the constructor.
$accountKey = ['secretKey' => $accountSecretKey];

try {
    $response = $unitpay->payments()->offsetAdvance($login, 3403575, $accountKey);
    var_dump($response->result ?? $response->error ?? $response);
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
