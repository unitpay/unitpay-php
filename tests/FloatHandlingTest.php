<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Unitpay;

/**
 * Locale-independent float handling across the layers (SignatureBuilder::build()'s
 * is_float branch, floatToString(), stringifyFloats()). Invariant: the signature is built
 * over the same decimal strings that go into the query string, so backend verification
 * matches even in a locale that uses a comma as the decimal separator.
 */
final class FloatHandlingTest extends TestCase
{
    private const SECRET = 'secret';

    private Unitpay $unitpay;
    private SignatureBuilder $signature;

    protected function setUp(): void
    {
        $this->unitpay = new Unitpay('unitpay.ru', self::SECRET);
        $this->signature = new SignatureBuilder();
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function sign(array $params): string
    {
        return $this->signature->build($params, self::SECRET);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testSignatureRendersFloatAsCanonicalDecimalString(): void
    {
        $this->assertSame(
            hash('sha256', '100.5{up}secret'),
            $this->sign(['sum' => 100.5])
        );
    }

    /** A whole float ("100.0") yields "100" — the same as the canonical string, so the signature matches regardless of type. */
    public function testWholeFloatMatchesCanonicalStringSignature(): void
    {
        $this->assertSame(
            $this->sign(['sum' => '100']),
            $this->sign(['sum' => 100.0])
        );
    }

    public function testFormRendersFloatSumAsCanonicalDecimalString(): void
    {
        $query = $this->queryOf($this->unitpay->form('pk', 100.5, 'acc', 'desc'));

        $this->assertSame('100.5', $query['sum']);
    }

    /** The trailing zero is stripped: 100.0 becomes "100" in the query string, not "100.00000000". */
    public function testFormStripsTrailingZeroFromWholeFloatSum(): void
    {
        $query = $this->queryOf($this->unitpay->form('pk', 100.0, 'acc', 'desc'));

        $this->assertSame('100', $query['sum']);
    }

    /**
     * Key invariant: the form signature is built over the same sum string that goes into
     * the query string. A regression here (signing the float, sending a different string
     * representation) would break backend signature verification for any fractional sum.
     */
    public function testFormSignatureCoversTheExactStringSumSentInQuery(): void
    {
        $query = $this->queryOf($this->unitpay->form('pk', 100.5, 'acc', 'desc'));

        $this->assertSame(
            $this->sign([
                'account'  => 'acc',
                'currency' => 'RUB',
                'desc'     => 'desc',
                'sum'      => $query['sum'],
            ]),
            $query['signature']
        );
    }

    public function testServiceCallRendersFloatSumAsCanonicalDecimalString(): void
    {
        $transport = new FakeTransport();
        $unitpay = new Unitpay('unitpay.test', self::SECRET, $transport);

        $unitpay->payments()->initPayment('order-1', 100.5, 1, 'card');

        $this->assertSame('100.5', $transport->query()['sum']);
    }
}
