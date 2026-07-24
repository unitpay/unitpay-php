<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Unitpay;

final class ReferenceServiceTest extends TestCase
{
    private function unitpay(FakeTransport $transport): Unitpay
    {
        return new Unitpay('unitpay.test', 'secret', $transport);
    }

    public function testGetMethodsAvailableSendsProjectId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->reference()->getMethodsAvailable(7);

        $query = $transport->query();
        $this->assertSame('getMethodsAvailable', $query['method']);
        $this->assertSame('7', $query['projectId']);
    }

    public function testGetCommissionsSendsProjectIdAndLogin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->reference()->getCommissions(7, 'partner@example.com');

        $query = $transport->query();
        $this->assertSame('getCommissions', $query['method']);
        $this->assertSame('7', $query['projectId']);
        $this->assertSame('partner@example.com', $query['login']);
    }

    public function testGetCurrencyCoursesSendsLogin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->reference()->getCurrencyCourses('partner@example.com');

        $query = $transport->query();
        $this->assertSame('getCurrencyCourses', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
    }

    public function testGetPartnerSendsLogin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->reference()->getPartner('partner@example.com');

        $query = $transport->query();
        $this->assertSame('getPartner', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
    }
}
