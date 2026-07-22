<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Фискальный чек по 54-ФЗ: позиции описываются объектами CashItem и прикрепляются к
 * платежу через setCashItems(). Чек уходит вместе со следующим вызовом form()/api() и
 * очищается после успешного вызова. Чтобы покупатель получил чек, укажите его контакт
 * (email и/или телефон) через setCustomerEmail()/setCustomerPhone().
 *
 * Справочники ставок НДС (NDS_*), предметов расчёта (PAYMENT_OBJECT_*), способов
 * расчёта (PAYMENT_METHOD_*) и единиц измерения (MEASURE_*) — это константы CashItem.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

// Позиция 1: товар. Аргументы конструктора: name, count, price, nds, предмет расчёта,
// способ расчёта. С 2026 бэкенд фискализирует vat20 как 22% — выбирайте по реальному чеку.
$item = new CashItem(
    $itemName,
    1,
    900,
    CashItem::NDS_20,
    CashItem::PAYMENT_OBJECT_COMMODITY,
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);
// Необязательные поля сериализуются, только если заданы (напр. единица измерения):
$item->setMeasure(CashItem::MEASURE_ITEM);

// Позиция 2: услуга (доставка), без НДС.
$delivery = new CashItem(
    'Доставка',
    1,
    150,
    CashItem::NDS_NONE,
    CashItem::PAYMENT_OBJECT_SERVICE,
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);

try {
    // Сумма платежа должна совпадать с суммой позиций чека: 900 + 150 = 1050.
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

    // Тот же чек можно прикрепить и к платёжной форме:
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
    // UnitpayValidationException, если имя позиции не в UTF-8 (json_encode вернёт false).
    print 'SDK error: ' . $e->getMessage();
}
