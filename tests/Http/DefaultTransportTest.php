<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Unitpay\Http\CurlTransport;
use Unitpay\Http\DefaultTransport;
use Unitpay\Http\RetryingTransport;

/**
 * The default transport stack.
 *
 * CHANGELOG and docs/getting-started.md both promise that retries are on out of the box.
 * Nothing else in the suite would notice if that stopped being true — every other test
 * injects a FakeTransport and never sees the default wiring at all.
 */
final class DefaultTransportTest extends TestCase
{
    /** Also pins the documented 5s/10s defaults, which the stack must not quietly retune. */
    public function testCreateWrapsCurlInTheRetryPolicy(): void
    {
        $transport = DefaultTransport::create();
        $this->assertInstanceOf(RetryingTransport::class, $transport);

        // assertInstanceOf does not narrow for PHPStan without phpstan/phpstan-phpunit,
        // and the declared return type is the interface — so state the type once here.
        /** @var RetryingTransport $transport */
        $inner = $transport->getInner();
        $this->assertInstanceOf(CurlTransport::class, $inner);

        /** @var CurlTransport $inner */
        $this->assertSame(5, $inner->getConnectTimeout());
        $this->assertSame(10, $inner->getTimeout());
    }

    /** The documented off switch. */
    public function testWithoutRetriesReturnsBareCurl(): void
    {
        $this->assertInstanceOf(CurlTransport::class, DefaultTransport::withoutRetries());
    }

    /** Each call builds its own stack: two clients must not share one transport instance. */
    public function testEachCallReturnsAFreshStack(): void
    {
        $this->assertNotSame(DefaultTransport::create(), DefaultTransport::create());
    }
}
