<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Unitpay;

final class SubscriptionServiceTest extends TestCase
{
    private function unitpay(FakeTransport $transport): Unitpay
    {
        return new Unitpay('unitpay.test', 'secret', $transport);
    }

    public function testListSubscriptionsSendsProjectId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->subscriptions()->listSubscriptions(7);

        $query = $transport->query();
        $this->assertSame('listSubscriptions', $query['method']);
        $this->assertSame('7', $query['projectId']);
    }

    /** 'all' widens the listing to closed subscriptions too. */
    public function testListSubscriptionsPassesOptionsThrough(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->subscriptions()->listSubscriptions(7, ['all' => 1]);

        $this->assertSame('1', $transport->query()['all']);
    }

    public function testGetSubscriptionSendsSubscriptionId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->subscriptions()->getSubscription(123);

        $query = $transport->query();
        $this->assertSame('getSubscription', $query['method']);
        $this->assertSame('123', $query['subscriptionId']);
    }

    public function testCloseSubscriptionSendsSubscriptionId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->subscriptions()->closeSubscription(123);

        $query = $transport->query();
        $this->assertSame('closeSubscription', $query['method']);
        $this->assertSame('123', $query['subscriptionId']);
    }
}
