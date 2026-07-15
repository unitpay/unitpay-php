<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPaySignatureTest extends TestCase
{
    /** @var UnitPay */
    private $unitPay;

    protected function setUp(): void
    {
        $this->unitPay = new UnitPay('unitpay.ru', 'secret');
    }

    public function testSignatureMatchesDocumentedFormula()
    {
        // sha256( <ksort'd values>{up}secretKey )
        $this->assertSame(
            hash('sha256', '1{up}secret'),
            $this->unitPay->getSignature(['a' => '1'])
        );
    }

    public function testSignatureIsIndependentOfKeyOrder()
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1', 'b' => '2']),
            $this->unitPay->getSignature(['b' => '2', 'a' => '1'])
        );
    }

    /**
     * Pin the sort DIRECTION to a literal: ksort is ascending by key, so keys
     * c,a,b become values 1,2,3. A krsort/asort refactor would change this digest
     * and break every multi-param production signature — this test would catch it.
     */
    public function testSignaturePinsAscendingKeyOrder()
    {
        $this->assertSame(
            hash('sha256', 'pay{up}1{up}2{up}3{up}secret'),
            $this->unitPay->getSignature(['c' => '3', 'a' => '1', 'b' => '2'], 'pay')
        );
    }

    public function testMethodIsPrependedToPayload()
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

    public function testCallerSuppliedSignatureKeysAreStripped()
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature(['a' => '1', 'sign' => 'x', 'signature' => 'y'])
        );
    }

    /**
     * Regression for F001: a crafted params[PHP_INT_MAX] must be stripped so it
     * cannot knock the auto-appended secretKey out of the hash (forgeable
     * signature on PHP <8, fatal Error on PHP >=8). Must not throw, and the
     * resulting signature must equal the one without the malicious key.
     */
    public function testPhpIntMaxKeyIsStrippedAndSecretRetained()
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature([PHP_INT_MAX => 'evil', 'a' => '1'])
        );
    }

    /**
     * An injected array value (e.g. webhook params[x][]=1) must not raise an
     * "Array to string conversion" warning; the array is coerced to '' and the
     * check simply fails to match a legitimate signature.
     */
    public function testArrayValuedParamDoesNotEmitWarning()
    {
        set_error_handler(static function ($errno, $errstr) {
            throw new \RuntimeException($errstr, $errno);
        });
        try {
            $signature = $this->unitPay->getSignature(['a' => ['nested']], 'pay');
        } finally {
            restore_error_handler();
        }

        // '' substituted for the array, so it matches an empty-valued param.
        $this->assertSame(
            $this->unitPay->getSignature(['a' => ''], 'pay'),
            $signature
        );
    }
}
