<?php

namespace Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Unitpay\Unitpay;
use Unitpay\Webhook\WebhookVerifier;

final class HandlerResponseTest extends TestCase
{
    private WebhookVerifier $webhook;

    protected function setUp(): void
    {
        $this->webhook = (new Unitpay('unitpay.ru', 'secret'))->webhook();
    }

    public function testSuccessHandlerResponseShape(): void
    {
        $this->assertSame(
            '{"result":{"message":"ok"}}',
            $this->webhook->getSuccessHandlerResponse('ok')
        );
    }

    public function testErrorHandlerResponseShape(): void
    {
        $this->assertSame(
            '{"error":{"message":"bad"}}',
            $this->webhook->getErrorHandlerResponse('bad')
        );
    }
}
