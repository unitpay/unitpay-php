<?php

namespace Tests;

use CashItem;
use InvalidArgumentException;
use UnexpectedValueException;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayApiTest extends TestCase
{
    public function testInitPaymentReturnsDecodedResponseViaInjectedTransport(): void
    {
        $transport = static function () {
            return '{"result":{"receiptId":42}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $transport);

        $response = $unitPay->api('initPayment', [
            'account'     => 1,
            'sum'         => 100,
            'projectId'   => 7,
            'paymentType' => 'card',
        ]);

        $this->assertSame(42, $response->result->receiptId);
    }

    public function testRequestUrlCarriesMethodParamsAndSecret(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);

        $unitPay->api('getPayment', ['paymentId' => 555]);

        $this->assertStringStartsWith('https://unitpay.test/api?', $captured);
        $this->assertStringContainsString('method=getPayment', $captured);
        $this->assertStringContainsString('paymentId', $captured);
        $this->assertStringContainsString('555', $captured);
        $this->assertStringContainsString('my-secret', $captured);
    }

    public function testRequestUrlUsesFlatParamsNotNested(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);

        $unitPay->api('getPayment', ['paymentId' => 555]);

        // Unitpay accepts flat query-string params since 05/2026 — no legacy params[...] nesting.
        $this->assertStringContainsString('paymentId=555', $captured);
        $this->assertStringContainsString('secretKey=my-secret', $captured);
        $this->assertStringNotContainsString('params%5B', $captured);
        $this->assertStringNotContainsString('params[', $captured);
    }

    public function testPayoutRequestUrlUsesFlatParams(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);

        $unitPay->api('massPayment', [
            'login'         => 'partner@example.com',
            'transactionId' => 1782,
            'sum'           => 10,
            'purse'         => '79510000071',
            'paymentType'   => 'sbp',
        ]);

        $this->assertStringContainsString('method=massPayment', $captured);
        $this->assertStringContainsString('transactionId=1782', $captured);
        $this->assertStringContainsString('purse=79510000071', $captured);
        $this->assertStringNotContainsString('params%5B', $captured);
    }

    /**
     * Params accumulated by the fluent setters (setCashItems/setCustomerEmail/…) must
     * reach the api() request, not just form(). Regression guard: api() used to build the
     * URL only from the $params argument and silently drop them.
     */
    public function testCashItemsFromSetterAreSentByApi(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);
        $unitPay->setCashItems([new CashItem('Coffee', 1, 100.0)])
            ->setCustomerEmail('buyer@example.com');

        $unitPay->api('initPayment', [
            'account'     => 1,
            'sum'         => 100,
            'projectId'   => 7,
            'paymentType' => 'card',
        ]);

        $this->assertStringContainsString('cashItems=', $captured);
        $this->assertStringContainsString('customerEmail=', $captured);

        parse_str((string) parse_url($captured, PHP_URL_QUERY), $q);
        $items = json_decode(base64_decode($q['cashItems']), true);
        $this->assertSame('Coffee', $items[0]['name']);
    }

    /** Explicit api() params take precedence over anything set by the fluent setters. */
    public function testExplicitApiParamOverridesAccumulatedParam(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);
        $unitPay->setBackUrl('https://old.example/back');

        $unitPay->api('initPayment', [
            'account'     => 1,
            'sum'         => 100,
            'projectId'   => 7,
            'paymentType' => 'card',
            'backUrl'     => 'https://new.example/back',
        ]);

        parse_str((string) parse_url($captured, PHP_URL_QUERY), $q);
        $this->assertSame('https://new.example/back', $q['backUrl']);
    }

    /**
     * Fluent-setter params are cleared by a successful api() call and must not leak into
     * the next call on a reused instance (regression: a stale cashItems receipt or
     * customerEmail would otherwise go out with an unrelated later order).
     */
    public function testFluentSetterParamsDoNotBleedIntoNextApiCall(): void
    {
        $urls = [];
        $transport = static function ($url) use (&$urls) {
            $urls[] = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);

        $unitPay->setCashItems([new CashItem('Coffee', 1, 100.0)])
            ->setCustomerEmail('buyer@example.com');
        $unitPay->api('initPayment', [
            'account'     => 1,
            'sum'         => 100,
            'projectId'   => 7,
            'paymentType' => 'card',
        ]);

        // The second call, without re-setting the receipt/customer, must be clean.
        $unitPay->api('getPayment', ['paymentId' => 555]);

        $this->assertStringContainsString('cashItems=', $urls[0]);
        $this->assertStringNotContainsString('cashItems=', $urls[1]);
        $this->assertStringNotContainsString('customerEmail=', $urls[1]);
    }

    /**
     * Fluent-setter params are cleared once the request has been attempted — on a transport
     * failure too, not only on success — so a stale receipt cannot leak into an unrelated
     * later call on a reused instance. A retry must re-apply the setters (symmetric with form()).
     */
    public function testFluentSetterParamsAreClearedAfterFailedApiCall(): void
    {
        $urls = [];
        $calls = 0;
        // The first call simulates a transport failure (false), later ones succeed.
        $transport = static function ($url) use (&$urls, &$calls) {
            $urls[] = $url;
            $calls++;
            return $calls === 1 ? false : '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);
        $unitPay->setCashItems([new CashItem('Coffee', 1, 100.0)]);

        try {
            $unitPay->api('getPayment', ['paymentId' => 1]);
            $this->fail('expected a transport exception on the first call');
        } catch (\UnitpayTransportException $e) {
            // expected: the transport returned false
        }

        $unitPay->api('getPayment', ['paymentId' => 2]);

        // The receipt was consumed by the failed call and did NOT leak into the next one.
        $this->assertStringContainsString('cashItems=', $urls[0]);
        $this->assertStringNotContainsString('cashItems=', $urls[1]);
    }

    public function testNonObjectResponseIsReportedAsTemporaryServerError(): void
    {
        $transport = static function () {
            return 'this is not json';
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $transport);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temporary server error');
        $unitPay->api('getPayment', ['paymentId' => 1]);
    }

    public function testUnsupportedMethodThrows(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Method is not supported');
        $unitPay->api('doesNotExist');
    }

    public function testMissingRequiredParamThrows(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        $this->expectException(InvalidArgumentException::class);
        // initPayment requires account, sum, projectId, paymentType
        $unitPay->api('initPayment', ['account' => 1]);
    }

    public function testMissingSecretThrows(): void
    {
        $unitPay = new UnitPay('unitpay.test', null, static function () {
            return '{"result":{}}';
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $unitPay->api('getPayment', ['paymentId' => 1]);
    }

    public function testPayoutMethodsAreSupportedAndValidateRequiredParams(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        $methods = [
            'massPayment',
            'massPaymentStatus',
            'massPaymentAvailableAmount',
            'massPaymentCommissions',
            'getSbpBankList',
            'getBinInfo',
        ];

        foreach ($methods as $method) {
            try {
                $unitPay->api($method, []);
                $this->fail($method . ' should require params');
            } catch (UnexpectedValueException $e) {
                $this->fail($method . ' is not in the allowlist');
            } catch (InvalidArgumentException $e) {
                // each payout method requires login first
                $this->assertStringContainsString('login', $e->getMessage());
            }
        }
    }

    /** A transport failure is a typed exception, still catchable as InvalidArgumentException. */
    public function testTransportFailureThrowsTypedTransportException(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return false; // simulate a transport failure
        });

        try {
            $unitPay->api('getPayment', ['paymentId' => 1]);
            $this->fail('expected a transport exception');
        } catch (\UnitpayTransportException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertStringContainsString('Temporary server error', $e->getMessage());
        }
    }

    /** An unsupported method throws a typed exception, still catchable as UnexpectedValueException. */
    public function testUnsupportedMethodThrowsTypedException(): void
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        try {
            $unitPay->api('doesNotExist');
            $this->fail('expected an unsupported-method exception');
        } catch (\UnitpayUnsupportedMethodException $e) {
            $this->assertInstanceOf(UnexpectedValueException::class, $e);
        }
    }

    /** Account-level methods can override the project key with the account key (secretKey). */
    public function testExplicitSecretKeyOverridesInstanceKey(): void
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'project-key', $transport);

        $unitPay->api('getPartner', [
            'login'     => 'partner@example.com',
            'secretKey' => 'account-key',
        ]);

        parse_str((string) parse_url($captured, PHP_URL_QUERY), $q);
        $this->assertSame('account-key', $q['secretKey']);
    }
}
