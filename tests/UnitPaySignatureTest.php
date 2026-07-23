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

    public function testSignatureMatchesDocumentedFormula(): void
    {
        // sha256( <значения, отсортированные ksort>{up}secretKey )
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
     * Фиксирует НАПРАВЛЕНИЕ сортировки конкретным значением: ksort сортирует по ключу
     * по возрастанию, поэтому ключи c,a,b дают значения 1,2,3. Рефакторинг на
     * krsort/asort изменил бы этот хэш и сломал бы каждую боевую подпись с несколькими
     * параметрами — этот тест такое поймает.
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
     * Регресс-тест: подделанный params[PHP_INT_MAX] должен быть убран, чтобы не
     * вытеснить автоматически добавляемый secretKey из хэша (подделываемая подпись на
     * PHP <8, фатальная Error на PHP >=8). Не должен бросать исключение, а полученная
     * подпись должна совпадать с подписью без вредоносного ключа.
     */
    public function testPhpIntMaxKeyIsStrippedAndSecretRetained(): void
    {
        $this->assertSame(
            $this->unitPay->getSignature(['a' => '1']),
            $this->unitPay->getSignature([PHP_INT_MAX => 'evil', 'a' => '1'])
        );
    }

    /**
     * Подсунутое значение-массив (например, вебхук params[x][]=1) не должно вызывать
     * предупреждение "Array to string conversion"; массив приводится к '', и проверка
     * просто не совпадает с легитимной подписью.
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

        // '' подставлено вместо массива, поэтому совпадает с параметром с пустым значением.
        $this->assertSame(
            $this->unitPay->getSignature(['a' => ''], 'pay'),
            $signature
        );
    }
}
