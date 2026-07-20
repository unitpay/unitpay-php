<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Интеграция через API
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

/**
 * Базовые параметры: account, desc, sum, currency, projectId, paymentType
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

// Сценарий с редиректом: отправляем пользователя на платёжный шлюз.
if (isset($response->result->type)
    && $response->result->type === 'redirect') {
    $redirectUrl = $response->result->redirectUrl;
    $paymentId = $response->result->paymentId;
    header("Location: " . $redirectUrl);

    // Сценарий со счётом (без редиректа).
} elseif (isset($response->result->type)
    && $response->result->type === 'invoice') {
    $receiptUrl = $response->result->receiptUrl;
    $paymentId = $response->result->paymentId;
    $invoiceId = $response->result->invoiceId;
    header("Location: " . $receiptUrl);

    // Обработано без редиректа (например, рекуррентное/подписное списание).
} elseif (isset($response->result->type)
    && $response->result->type === 'response') {
    $paymentId = $response->result->paymentId;
    $message = $response->result->message;
    // Необязательная страница статуса в Unitpay: $response->result->statusUrl
    print $message;

} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
