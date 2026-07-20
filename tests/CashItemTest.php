<?php

namespace Tests;

use CashItem;
use PHPUnit\Framework\TestCase;

final class CashItemTest extends TestCase
{
    public function testConstructorStoresRequiredFieldsAndFiscalDefaults()
    {
        $item = new CashItem('Coffee', 2, 150.5);

        $this->assertSame('Coffee', $item->getName());
        $this->assertSame(2, $item->getCount());
        $this->assertSame(150.5, $item->getPrice());
        $this->assertSame(CashItem::NDS_NONE, $item->getNds());
        $this->assertSame(CashItem::PAYMENT_OBJECT_COMMODITY, $item->getType());
        $this->assertSame(CashItem::PAYMENT_METHOD_PREPAYMENT_FULL, $item->getPaymentMethod());
    }

    public function testConstructorAcceptsExplicitFiscalFields()
    {
        $item = new CashItem(
            'Service',
            1,
            999.99,
            CashItem::NDS_20,
            CashItem::PAYMENT_OBJECT_SERVICE,
            CashItem::PAYMENT_METHOD_PAYMENT_FULL
        );

        $this->assertSame(CashItem::NDS_20, $item->getNds());
        $this->assertSame(CashItem::PAYMENT_OBJECT_SERVICE, $item->getType());
        $this->assertSame(CashItem::PAYMENT_METHOD_PAYMENT_FULL, $item->getPaymentMethod());
    }

    public function testOptionalGettersDefaultToNull()
    {
        $item = new CashItem('X', 1, 1.0);

        $this->assertNull($item->getSum());
        $this->assertNull($item->getCurrency());
        $this->assertNull($item->getMeasure());
        $this->assertNull($item->getNomenclatureCode());
        $this->assertNull($item->getMarkCode());
        $this->assertNull($item->getMarkQuantity());
        $this->assertNull($item->getPreText());
        $this->assertNull($item->getPostText());
    }

    public function testFluentSettersReturnSelfAndStoreValues()
    {
        $item = new CashItem('X', 1, 1.0);

        $this->assertSame($item, $item->setSum(100.5));
        $this->assertSame($item, $item->setCurrency('USD'));
        $this->assertSame($item, $item->setMeasure(CashItem::MEASURE_KG));
        $this->assertSame($item, $item->setNomenclatureCode('04620034587217'));
        $this->assertSame($item, $item->setMarkCode('mark-1'));
        $this->assertSame($item, $item->setPreText('before'));
        $this->assertSame($item, $item->setPostText('after'));

        $this->assertSame(100.5, $item->getSum());
        $this->assertSame('USD', $item->getCurrency());
        $this->assertSame(CashItem::MEASURE_KG, $item->getMeasure());
        $this->assertSame('04620034587217', $item->getNomenclatureCode());
        $this->assertSame('mark-1', $item->getMarkCode());
        $this->assertSame('before', $item->getPreText());
        $this->assertSame('after', $item->getPostText());
    }

    public function testSetMarkQuantityStoresIntegerFraction()
    {
        $item = new CashItem('X', 1, 1.0);

        $this->assertSame($item, $item->setMarkQuantity(1, 3));
        $this->assertSame(['numerator' => 1, 'denominator' => 3], $item->getMarkQuantity());
    }

    /** Нулевой знаменатель (или неположительная дробь) отклоняется, а не сохраняется молча. */
    public function testSetMarkQuantityRejectsNonPositiveValues()
    {
        $item = new CashItem('X', 1, 1.0);

        $this->expectException(\InvalidArgumentException::class);
        $item->setMarkQuantity(1, 0);
    }

    /** count должен быть положительным числом. */
    public function testConstructorRejectsNonPositiveCount()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CashItem('X', 0, 10.0);
    }

    /** price должен быть неотрицательным. */
    public function testConstructorRejectsNegativePrice()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CashItem('X', 1, -5.0);
    }

    /** Нечисловой count должен быть отклонён, а не проскочить проверку диапазона. */
    public function testConstructorRejectsNonNumericCount()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CashItem('X', 'abc', 10.0);
    }

    /** Нечисловой price должен быть отклонён, а не проскочить проверку диапазона. */
    public function testConstructorRejectsNonNumericPrice()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CashItem('X', 1, 'xyz');
    }

    /** Числовые строки принимаются и нормализуются в int/float. */
    public function testConstructorNormalizesNumericStrings()
    {
        $item = new CashItem('X', '3', '9.5');

        $this->assertSame(3, $item->getCount());
        $this->assertSame(9.5, $item->getPrice());
    }

    /** Дробные количества (весовые/объёмные товары) сохраняются, а не усекаются до int. */
    public function testConstructorPreservesFractionalCount()
    {
        $item = new CashItem('Cheese', 1.5, 500.0);

        $this->assertSame(1.5, $item->getCount());
    }
}
