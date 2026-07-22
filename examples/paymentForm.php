<?php

/**
 * Платёжная форма на стороне Unitpay: form() строит URL на его платёжную страницу. Перед
 * form() можно в fluent-стиле прикрепить необязательные параметры — backUrl (куда
 * вернуть плательщика), контакт покупателя, фискальный чек (см. receipt.php). Эти
 * параметры подмешиваются в запрос и очищаются после успешного form().
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
