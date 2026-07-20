<?php

/**
 * Платёжная форма
 *
 * @link https://help.unitpay.ru/payments/create-payment-easy
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$redirectUrl = $unitpay->form(
    $publicId,
    $orderSum,
    $orderId,
    $orderDesc,
    $orderCurrency
);

header("Location: " . $redirectUrl);
