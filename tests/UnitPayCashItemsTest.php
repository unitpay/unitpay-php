<?php

namespace Tests;

use CashItem;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayCashItemsTest extends TestCase
{
    /**
     * setCashItems() хранит в params base64(json(...)); единственный публичный способ
     * прочитать это обратно — через строку запроса формы, поэтому декодируем оттуда.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializedItems(UnitPay $unitPay): array
    {
        $url = $unitPay->form('pk', 1, 'acc', 'desc');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return json_decode(base64_decode($q['cashItems']), true);
    }

    public function testRequiredFieldsAreAlwaysSerialized(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setCashItems([
            new CashItem(
                'Coffee',
                2,
                150.5,
                CashItem::NDS_20,
                CashItem::PAYMENT_OBJECT_COMMODITY,
                CashItem::PAYMENT_METHOD_PAYMENT_FULL
            ),
        ]);

        $items = $this->serializedItems($unitPay);

        $this->assertCount(1, $items);
        $this->assertSame('Coffee', $items[0]['name']);
        $this->assertSame(2, $items[0]['count']);
        $this->assertSame(150.5, $items[0]['price']);
        $this->assertSame('vat20', $items[0]['nds']);
        $this->assertSame('commodity', $items[0]['type']);
        $this->assertSame('full_payment', $items[0]['paymentMethod']);
    }

    public function testOptionalFieldsAreOmittedWhenNotSet(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setCashItems([new CashItem('X', 1, 10.0)]);

        $items = $this->serializedItems($unitPay);

        foreach (['sum', 'currency', 'measure', 'nomenclatureCode', 'markCode', 'markQuantity', 'pre_text', 'post_text'] as $optional) {
            $this->assertArrayNotHasKey($optional, $items[0], "Optional key '$optional' must be absent when unset");
        }
    }

    public function testOptionalFieldsAreSerializedWhenSet(): void
    {
        $item = new CashItem('Y', 1, 10.5);
        $item->setSum(10.5)
            ->setCurrency('USD')
            ->setMeasure(CashItem::MEASURE_KG)
            ->setNomenclatureCode('NC-1')
            ->setMarkCode('MC-1')
            ->setPreText('pre')
            ->setPostText('post')
            ->setMarkQuantity(1, 2);

        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setCashItems([$item]);

        $items = $this->serializedItems($unitPay);

        $this->assertSame(10.5, $items[0]['sum']);
        $this->assertSame('USD', $items[0]['currency']);
        $this->assertSame(11, $items[0]['measure']);
        $this->assertSame('NC-1', $items[0]['nomenclatureCode']);
        $this->assertSame('MC-1', $items[0]['markCode']);
        $this->assertSame(['numerator' => 1, 'denominator' => 2], $items[0]['markQuantity']);
        $this->assertSame('pre', $items[0]['pre_text']);
        $this->assertSame('post', $items[0]['post_text']);
    }

    public function testMultipleItemsKeepTheirOrder(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');
        $unitPay->setCashItems([
            new CashItem('A', 1, 1.5),
            new CashItem('B', 2, 2.5),
        ]);

        $items = $this->serializedItems($unitPay);

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]['name']);
        $this->assertSame('B', $items[1]['name']);
    }

    /**
     * Имя не в UTF-8 (например, из Windows-1251) обрушивает json_encode; setCashItems()
     * бросает исключение вместо тихой отправки пустого чека.
     */
    public function testSetCashItemsThrowsOnNonUtf8Name(): void
    {
        $unitPay = new UnitPay('unitpay.ru', 'secret');

        $this->expectException(\UnitpayValidationException::class);
        $this->expectExceptionMessage('Failed to encode cashItems');
        $unitPay->setCashItems([new CashItem("\xB0Coffee", 1, 100.0)]);
    }
}
