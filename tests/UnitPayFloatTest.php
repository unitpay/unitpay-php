<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

/**
 * Локале-независимая обработка float в подписи и URL (getSignature() ветка is_float,
 * floatToString(), stringifyFloats()). Инвариант: подпись строится над теми же
 * десятичными строками, что уходят в строку запроса, поэтому проверка на бэкенде совпадает
 * даже в локали с запятой как десятичным разделителем.
 */
final class UnitPayFloatTest extends TestCase
{
    /** @var UnitPay */
    private $unitPay;

    protected function setUp(): void
    {
        $this->unitPay = new UnitPay('unitpay.ru', 'secret');
    }

    private function queryOf($url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        return $q;
    }

    public function testSignatureRendersFloatAsCanonicalDecimalString()
    {
        $this->assertSame(
            hash('sha256', '100.5{up}secret'),
            $this->unitPay->getSignature(['sum' => 100.5])
        );
    }

    /** Целый float («100.0») даёт «100» — то же, что каноническая строка, поэтому подпись совпадает независимо от типа. */
    public function testWholeFloatMatchesCanonicalStringSignature()
    {
        $this->assertSame(
            $this->unitPay->getSignature(['sum' => '100']),
            $this->unitPay->getSignature(['sum' => 100.0])
        );
    }

    public function testFormRendersFloatSumAsCanonicalDecimalString()
    {
        $q = $this->queryOf($this->unitPay->form('pk', 100.5, 'acc', 'desc'));

        $this->assertSame('100.5', $q['sum']);
    }

    /** Хвостовой ноль убирается: 100.0 в строке запроса становится «100», а не «100.00000000». */
    public function testFormStripsTrailingZeroFromWholeFloatSum()
    {
        $q = $this->queryOf($this->unitPay->form('pk', 100.0, 'acc', 'desc'));

        $this->assertSame('100', $q['sum']);
    }

    /**
     * Ключевой инвариант: подпись формы построена над той же строкой sum, что уходит в
     * строку запроса. Регресс здесь (подписали float, отправили другое строковое представление)
     * сломал бы проверку подписи на бэкенде для любой дробной суммы.
     */
    public function testFormSignatureCoversTheExactStringSumSentInQuery()
    {
        $q = $this->queryOf($this->unitPay->form('pk', 100.5, 'acc', 'desc'));

        $expected = $this->unitPay->getSignature([
            'account'  => 'acc',
            'currency' => 'RUB',
            'desc'     => 'desc',
            'sum'      => $q['sum'],
        ]);
        $this->assertSame($expected, $q['signature']);
    }

    public function testApiRendersFloatSumAsCanonicalDecimalString()
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $transport);

        $unitPay->api('initPayment', [
            'account'     => 'order-1',
            'sum'         => 100.5,
            'projectId'   => 1,
            'paymentType' => 'card',
        ]);

        parse_str((string) parse_url($captured, PHP_URL_QUERY), $q);
        $this->assertSame('100.5', $q['sum']);
    }
}
