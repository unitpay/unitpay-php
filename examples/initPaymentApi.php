<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * API integration
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Model\Enum\PaymentType;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';

$unitpay = new Unitpay($domain, $secretKey);

/**
 * initPayment takes its required params as arguments — account, sum, projectId,
 * paymentType — and everything else (desc, currency, ...) in the options array.
 * paymentType is a payment method code from the reference (the PaymentType constants):
 *   card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 * @link https://help.unitpay.ru/book-of-reference/payment-system-codes
 */
try {
    $response = $unitpay->payments()->initPayment(
        $orderId,
        $orderSum,
        $projectId,
        PaymentType::CARD,
        [
            'desc'     => $orderDesc,
            'currency' => $orderCurrency,
        ]
    );

    // The initPayment response comes in three types: redirect, invoice, response.
    switch ($response->result->type ?? null) {
        case 'redirect':
            // paymentId is in $response->result->paymentId; save it on your side.
            if (isset($response->result->redirectUrl)) {
                header('Location: ' . $response->result->redirectUrl);
                exit;
            }
            var_dump($response);
            break;

        case 'invoice':
            // Besides receiptUrl, $response->result->paymentId and ->invoiceId are available.
            if (isset($response->result->receiptUrl)) {
                header('Location: ' . $response->result->receiptUrl);
                exit;
            }
            var_dump($response);
            break;

        case 'response':
            // No redirect (e.g. a recurring charge); the status is in ->statusUrl.
            print $response->result->message ?? '';
            break;

        default:
            // No type — usually an API-level error.
            if (isset($response->error->message)) {
                print 'Error: ' . $response->error->message;
            } else {
                var_dump($response);
            }
    }
} catch (UnitpayExceptionInterface $exception) {
    // SDK-side failure: network, disabled allow_url_fopen, malformed JSON, etc.
    print 'SDK error: ' . $exception->getMessage();
}
