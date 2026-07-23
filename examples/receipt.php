<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * 54-FZ fiscal receipt: line items are described by CashItem objects and attached to
 * the payment via setCashItems(). The receipt goes out with the next form()/api() call
 * and is cleared after a successful call. For the customer to receive the receipt, set
 * their contact (email and/or phone) via setCustomerEmail()/setCustomerPhone().
 *
 * The dictionaries of VAT rates (NDS_*), payment objects (PAYMENT_OBJECT_*), payment
 * methods (PAYMENT_METHOD_*) and units of measure (MEASURE_*) are CashItem constants.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

// Line item 1: a commodity. Constructor args: name, count, price, nds, payment object,
// payment method. Since 2026 the backend fiscalizes vat20 as 22% — pick per the real receipt.
$item = new CashItem(
    $itemName,
    1,
    900,
    CashItem::NDS_20,
    CashItem::PAYMENT_OBJECT_COMMODITY,
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);
// Optional fields are serialized only when set (e.g. unit of measure):
$item->setMeasure(CashItem::MEASURE_ITEM);

// Line item 2: a service (delivery), no VAT.
$delivery = new CashItem(
    'Доставка',
    1,
    150,
    CashItem::NDS_NONE,
    CashItem::PAYMENT_OBJECT_SERVICE,
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);

try {
    // The payment sum must match the sum of the receipt line items: 900 + 150 = 1050.
    $response = $unitpay
        ->setCustomerEmail('customer@example.com')
        ->setCashItems([$item, $delivery])
        ->api('initPayment', [
            'account'     => $orderId,
            'desc'        => $orderDesc,
            'sum'         => 1050,
            'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
            'currency'    => $orderCurrency,
            'projectId'   => $projectId,
        ]);

    // The same receipt can also be attached to the payment form:
    //   $url = $unitpay->setCashItems([$item, $delivery])
    //       ->form($publicId, 1050, $orderId, $orderDesc, $orderCurrency);

    if (isset($response->result->redirectUrl)) {
        header('Location: ' . $response->result->redirectUrl);
        exit;
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    } else {
        var_dump($response);
    }
} catch (UnitpayExceptionInterface $e) {
    // UnitpayValidationException if a line-item name is not UTF-8 (json_encode returns false).
    print 'SDK error: ' . $e->getMessage();
}
