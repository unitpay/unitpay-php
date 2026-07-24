<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * 54-FZ fiscal receipt: line items are described by CashItem objects and attached to
 * the payment via setCashItems(). The receipt goes out with the next form()/service call
 * and is cleared after a successful call. For the customer to receive the receipt, set
 * their contact (email and/or phone) via setCustomerEmail()/setCustomerPhone().
 *
 * The dictionaries of VAT rates, payment objects, payment methods and units of measure
 * are const-classes under Unitpay\Model\Enum: Nds, PaymentObject, PaymentMethod, Measure.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Model\CashItem;
use Unitpay\Model\Enum\Measure;
use Unitpay\Model\Enum\Nds;
use Unitpay\Model\Enum\PaymentMethod;
use Unitpay\Model\Enum\PaymentObject;
use Unitpay\Model\Enum\PaymentType;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';

$unitpay = new Unitpay($domain, $secretKey);

// Line item 1: a commodity. Constructor args: name, count, price, nds, payment object,
// payment method. Since 2026 the backend fiscalizes vat20 as 22% — pick per the real receipt.
$item = new CashItem(
    $itemName,
    1,
    900,
    Nds::VAT20,
    PaymentObject::COMMODITY,
    PaymentMethod::PAYMENT_FULL
);
// Optional fields are serialized only when set (e.g. unit of measure):
$item->setMeasure(Measure::ITEM);

// Line item 2: a service (delivery), no VAT.
$delivery = new CashItem(
    'Доставка',
    1,
    150,
    Nds::NONE,
    PaymentObject::SERVICE,
    PaymentMethod::PAYMENT_FULL
);

try {
    // The payment sum must match the sum of the receipt line items: 900 + 150 = 1050.
    $response = $unitpay
        ->setCustomerEmail('customer@example.com')
        ->setCashItems([$item, $delivery])
        ->payments()
        ->initPayment(
            $orderId,
            1050,
            $projectId,
            PaymentType::CARD,
            [
                'desc'     => $orderDesc,
                'currency' => $orderCurrency,
            ]
        );

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
} catch (UnitpayExceptionInterface $exception) {
    // UnitpayValidationException if a line-item name is not UTF-8 (json_encode returns false).
    print 'SDK error: ' . $exception->getMessage();
}
