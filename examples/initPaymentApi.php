<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * API integration
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

/**
 * Base params: account, desc, sum, currency, projectId, paymentType
 * paymentType — код способа оплаты из справочника (константы UnitPay::PAYMENT_TYPE_*):
 *   card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 * @link https://help.unitpay.ru/book-of-reference/payment-system-codes
 */
$response = $unitpay->api('initPayment', [
    'account' => $orderId,
    'desc' => $orderDesc,
    'sum' => $orderSum,
    'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
    'currency' => $orderCurrency,
    'projectId' => $projectId,
]);

// If need user redirect on Payment Gate
if (isset($response->result->type)
    && $response->result->type === 'redirect') {
    // Url on PaymentGate
    $redirectUrl = $response->result->redirectUrl;
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // User redirect
    header("Location: " . $redirectUrl);

    // If without redirect (invoice)
} elseif (isset($response->result->type)
    && $response->result->type === 'invoice') {
    // Url on receipt page in Unitpay
    $receiptUrl = $response->result->receiptUrl;
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // Invoice Id in Payment Gate (you can save it)
    $invoiceId = $response->result->invoiceId;
    // User redirect
    header("Location: " . $receiptUrl);

    // If processed without redirect (e.g. recurring/subscription charge)
} elseif (isset($response->result->type)
    && $response->result->type === 'response') {
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // Human-readable result message
    $message = $response->result->message;
    // Optional status page in Unitpay: $response->result->statusUrl
    print $message;

    // If error during api request
} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
