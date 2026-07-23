<?php

/**
 * Unitpay-hosted payment form: form() builds a URL to its payment page. Before form()
 * you can attach optional parameters in a fluent style — backUrl (where to return the
 * payer), the customer's contact, a fiscal receipt (see receipt.php). These parameters
 * are merged into the request and cleared after a successful form().
 *
 * @link https://help.unitpay.ru/payments/create-payment-easy
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

try {
    $redirectUrl = $unitpay
        ->setBackUrl('https://example.com/order/' . $orderId)
        ->setCustomerEmail('customer@example.com')
        ->setCustomerPhone('79000000000')
        ->form(
            $publicId,
            $orderSum,
            $orderId,
            $orderDesc,
            $orderCurrency
        );

    header("Location: " . $redirectUrl);
    exit;
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
