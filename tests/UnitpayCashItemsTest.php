<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Model\CashItem;
use Unitpay\Model\Enum\Measure;
use Unitpay\Model\Enum\Nds;
use Unitpay\Model\Enum\PaymentMethod;
use Unitpay\Model\Enum\PaymentObject;
use Unitpay\Unitpay;

final class UnitpayCashItemsTest extends TestCase
{
    /**
     * setCashItems() stores base64(json(...)) in the pending params; the only public way
     * to read it back is via the form's query string, so we decode it from there.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializedItems(Unitpay $unitpay): array
    {
        $url = $unitpay->form('pk', 1, 'acc', 'desc');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return json_decode(base64_decode((string) $query['cashItems']), true);
    }

    public function testRequiredFieldsAreAlwaysSerialized(): void
    {
        $unitpay = new Unitpay('unitpay.ru', 'secret');
        $unitpay->setCashItems([
            new CashItem(
                'Coffee',
                2,
                150.5,
                Nds::VAT20,
                PaymentObject::COMMODITY,
                PaymentMethod::PAYMENT_FULL
            ),
        ]);

        $items = $this->serializedItems($unitpay);

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
        $unitpay = new Unitpay('unitpay.ru', 'secret');
        $unitpay->setCashItems([new CashItem('X', 1, 10.0)]);

        $items = $this->serializedItems($unitpay);

        foreach (['sum', 'currency', 'measure', 'nomenclatureCode', 'markCode', 'markQuantity', 'pre_text', 'post_text'] as $optional) {
            $this->assertArrayNotHasKey($optional, $items[0], "Optional key '$optional' must be absent when unset");
        }
    }

    public function testOptionalFieldsAreSerializedWhenSet(): void
    {
        $item = new CashItem('Y', 1, 10.5);
        $item->setSum(10.5)
            ->setCurrency('USD')
            ->setMeasure(Measure::KG)
            ->setNomenclatureCode('NC-1')
            ->setMarkCode('MC-1')
            ->setPreText('pre')
            ->setPostText('post')
            ->setMarkQuantity(1, 2);

        $unitpay = new Unitpay('unitpay.ru', 'secret');
        $unitpay->setCashItems([$item]);

        $items = $this->serializedItems($unitpay);

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
        $unitpay = new Unitpay('unitpay.ru', 'secret');
        $unitpay->setCashItems([
            new CashItem('A', 1, 1.5),
            new CashItem('B', 2, 2.5),
        ]);

        $items = $this->serializedItems($unitpay);

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]['name']);
        $this->assertSame('B', $items[1]['name']);
    }

    /**
     * A non-UTF-8 name (e.g. from Windows-1251) breaks json_encode; setCashItems()
     * throws instead of silently sending an empty receipt.
     */
    public function testSetCashItemsThrowsOnNonUtf8Name(): void
    {
        $unitpay = new Unitpay('unitpay.ru', 'secret');

        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('Failed to encode cashItems');
        $unitpay->setCashItems([new CashItem("\xB0Coffee", 1, 100.0)]);
    }
}
