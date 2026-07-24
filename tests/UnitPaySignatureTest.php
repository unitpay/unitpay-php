<?php

namespace Tests;

use UnitPay;
use UnitpayValidationException;
use PHPUnit\Framework\TestCase;

final class UnitPaySignatureTest extends TestCase
{
    private UnitPay $unitPay;

    protected function setUp(): void
    {
        $this->unitPay = new UnitPay('unitpay.ru', 'secret');
    }

    /**
     * Defense-in-depth: getSignature() is public, so a direct call with no secret must
     * throw rather than silently hash with an empty secret (the appended null coerces to
     * '' and drops out, yielding a plausible but secret-less signature).
     */
    public function testEmptySecretIsRejected(): void
    {
        $unitPay = new UnitPay('unitpay.ru', null);

        $this->expectException(UnitpayValidationException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $unitPay->getSignature(['a' => '1']);
    }

    public function testSignatureMatchesDocumentedFormula(): void
    {
        // sha256( <ksort-sorted values>{up}secretKey )
        $this->assertSame(
            hash('sha256', '1{up}secret'),
            $this->unitPay->getSignature(['a' => '1'])
        );
    }

    public function testSignatureIsIndependentOfKeyOrder(): void
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1', 'b' => '2']),
            $this->unitPay->getSignature(['b' => '2', 'a' => '1'])
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
            $this->unitPay->getSignature(['c' => '3', 'a' => '1', 'b' => '2'], 'pay')
        );
    }

    public function testMethodIsPrependedToPayload(): void
    {
        $this->assertSame(
            hash('sha256', 'pay{up}1{up}secret'),
            $this->unitPay->getSignature(['a' => '1'], 'pay')
        );
        $this->assertNotSame(
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature(['a' => '1'], 'pay')
        );
    }

    public function testCallerSuppliedSignatureKeysAreStripped(): void
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature(['a' => '1', 'sign' => 'x', 'signature' => 'y'])
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
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature([PHP_INT_MAX => 'evil', 'a' => '1'])
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
            $signature = $this->unitPay->getSignature(['a' => ['nested']], 'pay');
        } finally {
            restore_error_handler();
        }

        // '' is substituted for the array, so it matches a param with an empty value.
        $this->assertSame(
            $this->unitPay->getSignature(['a' => ''], 'pay'),
            $signature
        );
    }
}
