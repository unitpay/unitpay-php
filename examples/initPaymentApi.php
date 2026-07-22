<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Интеграция через API
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

/**
 * Базовые параметры: account, desc, sum, currency, projectId, paymentType
 * paymentType — код способа оплаты из справочника (константы UnitPay::PAYMENT_TYPE_*):
 *   card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 * @link https://help.unitpay.ru/book-of-reference/payment-system-codes
 */
try {
    $response = $unitpay->api('initPayment', [
        'account' => $orderId,
        'desc' => $orderDesc,
        'sum' => $orderSum,
        'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
        'currency' => $orderCurrency,
        'projectId' => $projectId,
    ]);

    // Ответ initPayment бывает трёх типов: redirect, invoice, response.
    switch ($response->result->type ?? null) {
        case 'redirect':
            // paymentId — в $response->result->paymentId, сохраните у себя.
            if (isset($response->result->redirectUrl)) {
                header('Location: ' . $response->result->redirectUrl);
                exit;
            }
            var_dump($response);
            break;

        case 'invoice':
            // Помимо receiptUrl доступны $response->result->paymentId и ->invoiceId.
            if (isset($response->result->receiptUrl)) {
                header('Location: ' . $response->result->receiptUrl);
                exit;
            }
            var_dump($response);
            break;

        case 'response':
            // Без перенаправления (например, рекуррентное списание); статус — в ->statusUrl.
            print $response->result->message ?? '';
            break;

        default:
            // Типа нет — как правило, ошибка уровня API.
            if (isset($response->error->message)) {
                print 'Error: ' . $response->error->message;
            } else {
                var_dump($response);
            }
    }
} catch (UnitpayExceptionInterface $e) {
    // Сбой на стороне SDK: сеть, отключённый allow_url_fopen, битый JSON и т.п.
    print 'SDK error: ' . $e->getMessage();
}
