<?php

namespace Tests;

use InvalidArgumentException;
use UnexpectedValueException;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayHandlerTest extends TestCase
{
    public const SECRET = 'secret';
    public const ALLOWED_IP = '31.186.100.49';

    /**
     * Builds a webhook array with a valid signature over its params.
     *
     * @param string               $method
     * @param array<string, mixed> $overrides params to add/override before signing
     * @return array{method: string, params: array<string, mixed>}
     */
    private function validRequest(string $method = 'pay', array $overrides = []): array
    {
        $params = array_merge([
            'account'       => '42',
            'orderSum'      => '100.00',
            'orderCurrency' => 'RUB',
            'date'          => '2026-07-20 12:00:00',
            'payerSum'      => '100.00',
            'unitpayId'     => '999',
        ], $overrides);

        $params['signature'] = $this->sign($params, $method);

        return ['method' => $method, 'params' => $params];
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function sign(array $params, string $method): string
    {
        return (new UnitPay('unitpay.ru', self::SECRET))->getSignature($params, $method);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function handler(array $request, string $ip = self::ALLOWED_IP, ?string $secret = self::SECRET): UnitPay
    {
        return new UnitPay('unitpay.ru', $secret, null, $request, $ip);
    }

    public function testValidSignatureAndAllowedIpPass(): void
    {
        $this->assertTrue($this->handler($this->validRequest('pay'))->checkHandlerRequest());
    }

    public function testTamperedParamsAreRejected(): void
    {
        $request = $this->validRequest('pay');
        $request['params']['orderSum'] = '0.01'; // changed after signing

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong signature');
        $this->handler($request)->checkHandlerRequest();
    }

    public function testDisallowedIpIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IP address Error');
        $this->handler($this->validRequest('pay'), '8.8.8.8')->checkHandlerRequest();
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $this->handler($this->validRequest('pay'), self::ALLOWED_IP, null)->checkHandlerRequest();
    }

    public function testMissingMethodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Method is null');
        $this->handler(['params' => ['x' => '1']])->checkHandlerRequest();
    }

    public function testMissingParamsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Params is null');
        $this->handler(['method' => 'pay'])->checkHandlerRequest();
    }

    public function testUnsupportedPartnerMethodIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Method is not supported');
        $this->handler($this->validRequest('refund'))->checkHandlerRequest();
    }

    /**
     * A two-stage hold notification (preauth) is a valid webhook method that Unitpay
     * sends (method = check | pay | preauth | error). It must pass verification rather
     * than be rejected as unsupported.
     */
    public function testPreauthPartnerMethodIsSupported(): void
    {
        $request = $this->validRequest('preauth', ['isPreauth' => '1']);

        $unitPay = $this->handler($request);

        $this->assertTrue($unitPay->checkHandlerRequest());
        $this->assertSame('preauth', $unitPay->getHandlerMethod());
    }

    /**
     * A non-string signature (e.g. an array injected via $_GET) must be cleanly rejected
     * as "Wrong signature" rather than cause a TypeError.
     */
    public function testArraySignatureIsRejectedCleanly(): void
    {
        $request = $this->validRequest('pay');
        $request['params']['signature'] = ['not', 'a', 'string'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong signature');
        $this->handler($request)->checkHandlerRequest();
    }

    /**
     * A forged params[PHP_INT_MAX] must not break verification and must pass it correctly
     * (the key is stripped both when signing and when verifying).
     */
    public function testPhpIntMaxKeyInParamsDoesNotBreakVerification(): void
    {
        $params = [
            'account'       => '42',
            'orderSum'      => '100.00',
            'orderCurrency' => 'RUB',
        ];
        $params[PHP_INT_MAX] = 'injected';
        $params['signature'] = $this->sign($params, 'pay');

        $request = ['method' => 'pay', 'params' => $params];

        $this->assertTrue($this->handler($request)->checkHandlerRequest());
    }

    public function testSetAllowedIpsOverridesTheDefaultAllowlist(): void
    {
        $customIp = '203.0.113.7'; // TEST-NET-3, not in the default list
        $unitPay = $this->handler($this->validRequest('pay'), $customIp);
        $unitPay->setAllowedIps([$customIp]);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    /** 127.0.0.1 is NOT trusted by default: behind a proxy on the same host it would nullify the IP check. */
    public function testLocalhostIsRejectedByDefault(): void
    {
        $unitPay = $this->handler($this->validRequest('pay'), '127.0.0.1');

        $this->expectException(\UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    /** setAllowedIps accepts CIDR subnets, not just exact IPs. */
    public function testCidrAllowlistMatchesAddressInRange(): void
    {
        $unitPay = $this->handler($this->validRequest('pay'), '203.0.113.55');
        $unitPay->setAllowedIps(['203.0.113.0/24']);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    public function testCidrAllowlistRejectsAddressOutOfRange(): void
    {
        $unitPay = $this->handler($this->validRequest('pay'), '203.0.114.1');
        $unitPay->setAllowedIps(['203.0.113.0/24']);

        $this->expectException(\UnitpayIpException::class);
        $unitPay->checkHandlerRequest();
    }

    /** CIDR matching works for IPv6 too (binary comparison via inet_pton). */
    public function testCidrAllowlistMatchesIpv6InRange(): void
    {
        $unitPay = $this->handler($this->validRequest('pay'), '2001:db8::1');
        $unitPay->setAllowedIps(['2001:db8::/32']);

        $this->assertTrue($unitPay->checkHandlerRequest());
    }

    /** Before the first successful verification, the verified-data getters return null. */
    public function testHandlerGettersAreNullBeforeVerification(): void
    {
        $unitPay = $this->handler($this->validRequest('pay'));

        $this->assertNull($unitPay->getHandlerMethod());
        $this->assertNull($unitPay->getHandlerParams());
    }

    /** After a successful verification, getHandlerParams() returns exactly the verified webhook params. */
    public function testGetHandlerParamsReturnsVerifiedParams(): void
    {
        $request = $this->validRequest('pay');
        $unitPay = $this->handler($request);

        $this->assertTrue($unitPay->checkHandlerRequest());
        $this->assertSame($request['params'], $unitPay->getHandlerParams());
        $this->assertSame('42', $unitPay->getHandlerParams()['account']);
    }

    /** A typed exception that still extends the historical SPL type + the marker interface. */
    public function testSignatureFailureThrowsTypedExceptionStillCatchableAsInvalidArgument(): void
    {
        $request = $this->validRequest('pay');
        $request['params']['orderSum'] = '0.01'; // changed after signing

        try {
            $this->handler($request)->checkHandlerRequest();
            $this->fail('expected a signature exception');
        } catch (\UnitpaySignatureException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertInstanceOf(\UnitpayExceptionInterface::class, $e);
        }
    }
}
