<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

/**
 * Locale-independent float handling in the signature and URL (the is_float branch of
 * getSignature(), floatToString(), stringifyFloats()). Invariant: the signature is built
 * over the same decimal strings that go into the query string, so backend verification
 * matches even in a locale that uses a comma as the decimal separator.
 */
final class UnitPayFloatTest extends TestCase
{
    private UnitPay $unitPay;

    protected function setUp(): void
    {
        $this->unitPay = new UnitPay('unitpay.ru', 'secret');
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        return $q;
    }

    public function testSignatureRendersFloatAsCanonicalDecimalString(): void
    {
        $this->assertSame(
            hash('sha256', '100.5{up}secret'),
            $this->unitPay->getSignature(['sum' => 100.5])
        );
    }

    /** A whole float ("100.0") yields "100" — the same as the canonical string, so the signature matches regardless of type. */
    public function testWholeFloatMatchesCanonicalStringSignature(): void
    {
        $this->assertSame(
            $this->unitPay->getSignature(['sum' => '100']),
            $this->unitPay->getSignature(['sum' => 100.0])
        );
    }

    public function testFormRendersFloatSumAsCanonicalDecimalString(): void
    {
        $q = $this->queryOf($this->unitPay->form('pk', 100.5, 'acc', 'desc'));

        $this->assertSame('100.5', $q['sum']);
    }

    /** The trailing zero is stripped: 100.0 becomes "100" in the query string, not "100.00000000". */
    public function testFormStripsTrailingZeroFromWholeFloatSum(): void
    {
        $q = $this->queryOf($this->unitPay->form('pk', 100.0, 'acc', 'desc'));

        $this->assertSame('100', $q['sum']);
    }

    /**
     * Key invariant: the form signature is built over the same sum string that goes into
     * the query string. A regression here (signing the float, sending a different string
     * representation) would break backend signature verification for any fractional sum.
     */
    public function testFormSignatureCoversTheExactStringSumSentInQuery(): void
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

    public function testApiRendersFloatSumAsCanonicalDecimalString(): void
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
