<?php

namespace Tests\Signature;

use PHPUnit\Framework\TestCase;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Signature\SignatureBuilder;

final class SignatureBuilderTest extends TestCase
{
    private const SECRET = 'secret';

    private SignatureBuilder $signature;

    protected function setUp(): void
    {
        $this->signature = new SignatureBuilder();
    }

    /**
     * Convenience wrapper pinning the secret, so each case reads as the payload it signs.
     * @param array<array-key, mixed> $params
     */
    private function sign(array $params, ?string $method = null): string
    {
        return $this->signature->build($params, self::SECRET, $method);
    }

    /**
     * Defense-in-depth: build() is a public entry point, so a call with no secret must
     * throw rather than silently hash with an empty secret (the appended null coerces to
     * '' and drops out, yielding a plausible but secret-less signature).
     */
    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $this->signature->build(['a' => '1'], null);
    }

    public function testSignatureMatchesDocumentedFormula(): void
    {
        // sha256( <ksort-sorted values>{up}secretKey )
        $this->assertSame(
            hash('sha256', '1{up}secret'),
            $this->sign(['a' => '1'])
        );
    }

    public function testSignatureIsIndependentOfKeyOrder(): void
    {
        $this->assertSame(
            $this->sign(['a' => '1', 'b' => '2']),
            $this->sign(['b' => '2', 'a' => '1'])
        );
    }

    /**
     * Pins the sort DIRECTION with a concrete value: ksort sorts by key ascending, so
     * keys c,a,b yield values 1,2,3. Refactoring to krsort/asort would change this hash
     * and break every production signature with multiple params — this test catches that.
     */
    public function testSignaturePinsAscendingKeyOrder(): void
    {
        $this->assertSame(
            hash('sha256', 'pay{up}1{up}2{up}3{up}secret'),
            $this->sign(['c' => '3', 'a' => '1', 'b' => '2'], 'pay')
        );
    }

    public function testMethodIsPrependedToPayload(): void
    {
        $this->assertSame(
            hash('sha256', 'pay{up}1{up}secret'),
            $this->sign(['a' => '1'], 'pay')
        );
        $this->assertNotSame(
            $this->sign(['a' => '1']),
            $this->sign(['a' => '1'], 'pay')
        );
    }

    public function testCallerSuppliedSignatureKeysAreStripped(): void
    {
        $this->assertSame(
            $this->sign(['a' => '1']),
            $this->sign(['a' => '1', 'sign' => 'x', 'signature' => 'y'])
        );
    }

    /**
     * Regression test: a forged params[PHP_INT_MAX] must be stripped so it cannot push the
     * automatically appended secretKey out of the hash (forgeable signature on PHP <8, a
     * fatal Error on PHP >=8). It must not throw, and the resulting signature must match
     * the signature without the malicious key.
     */
    public function testPhpIntMaxKeyIsStrippedAndSecretRetained(): void
    {
        $this->assertSame(
            $this->sign(['a' => '1']),
            $this->sign([PHP_INT_MAX => 'evil', 'a' => '1'])
        );
    }

    /**
     * An injected array value (e.g. a webhook params[x][]=1) must not raise an
     * "Array to string conversion" warning; the array is coerced to '', and verification
     * simply does not match a legitimate signature.
     */
    public function testArrayValuedParamDoesNotEmitWarning(): void
    {
        set_error_handler(static function ($errno, $errstr) {
            throw new \RuntimeException($errstr, $errno);
        });
        try {
            $signature = $this->sign(['a' => ['nested']], 'pay');
        } finally {
            restore_error_handler();
        }

        // '' is substituted for the array, so it matches a param with an empty value.
        $this->assertSame(
            $this->sign(['a' => ''], 'pay'),
            $signature
        );
    }

    /**
     * floatToString()/stringifyFloats() are locale-independent: (string) $float honors
     * LC_NUMERIC on PHP <8.0 and would yield "100,5" in comma locales, breaking the
     * signature/URL match.
     */
    public function testFloatToStringDropsTrailingZeros(): void
    {
        $this->assertSame('100.5', SignatureBuilder::floatToString(100.5));
        $this->assertSame('100', SignatureBuilder::floatToString(100.0));
    }

    public function testStringifyFloatsConvertsOnlyFloats(): void
    {
        $params = SignatureBuilder::stringifyFloats([
            'sum'     => 100.5,
            'count'   => 2,
            'account' => 'acc',
        ]);

        $this->assertSame('100.5', $params['sum']);
        $this->assertSame(2, $params['count']);
        $this->assertSame('acc', $params['account']);
    }
}
