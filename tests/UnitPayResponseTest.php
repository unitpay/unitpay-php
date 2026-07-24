<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayResponseTest extends TestCase
{
    private UnitPay $unitPay;

    protected function setUp(): void
    {
        $this->unitPay = new UnitPay('unitpay.ru', 'secret');
    }

    public function testSuccessHandlerResponseShape(): void
    {
        $this->assertSame(
            '{"result":{"message":"ok"}}',
            $this->unitPay->getSuccessHandlerResponse('ok')
        );
    }

    public function testErrorHandlerResponseShape(): void
    {
        $this->assertSame(
            '{"error":{"message":"bad"}}',
            $this->unitPay->getErrorHandlerResponse('bad')
        );
    }
}
