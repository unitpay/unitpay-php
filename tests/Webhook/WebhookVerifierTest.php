<?php

namespace Tests\Webhook;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Exception\UnitpayIpException;
use Unitpay\Exception\UnitpaySignatureException;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Unitpay;
use Unitpay\Webhook\WebhookVerifier;

final class WebhookVerifierTest extends TestCase
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
            // Fresh by default: the replay window is on out of the box, so a fixed date
            // would make every webhook test start failing the moment it went stale.
            'date'          => self::dateAt(time()),
            'payerSum'      => '100.00',
            'unitpayId'     => '999',
        ], $overrides);

        $params['signature'] = $this->sign($params, $method);

        return ['method' => $method, 'params' => $params];
    }

    /**
     * Formats a unix timestamp the way Unitpay sends it: `Y-m-d H:i:s` wall clock in
     * UTC+3. gmdate() rather than date() so the fixture does not shift with the ambient
     * date.timezone — which is exactly what the timezone regression test below checks.
     */
    public static function dateAt(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp + 3 * 3600);
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function sign(array $params, string $method): string
    {
        return (new SignatureBuilder())->build($params, self::SECRET, $method);
    }

    /**
     * The verifier as a consumer gets it — wired by the facade, with the inbound request
     * and sender IP injected instead of read from the superglobals.
     *
     * @param array<string, mixed> $request
     */
    private function handler(array $request, string $ip = self::ALLOWED_IP, ?string $secret = self::SECRET): WebhookVerifier
    {
        return (new Unitpay('unitpay.ru', $secret, null, $request, $ip))->webhook();
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

        $webhook = $this->handler($request);

        $this->assertTrue($webhook->checkHandlerRequest());
        $this->assertSame('preauth', $webhook->getHandlerMethod());
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
        $webhook = $this->handler($this->validRequest('pay'), $customIp);
        $webhook->setAllowedIps([$customIp]);

        $this->assertTrue($webhook->checkHandlerRequest());
    }

    /** 127.0.0.1 is NOT trusted by default: behind a proxy on the same host it would nullify the IP check. */
    public function testLocalhostIsRejectedByDefault(): void
    {
        $webhook = $this->handler($this->validRequest('pay'), '127.0.0.1');

        $this->expectException(UnitpayIpException::class);
        $webhook->checkHandlerRequest();
    }

    /** setAllowedIps accepts CIDR subnets, not just exact IPs. */
    public function testCidrAllowlistMatchesAddressInRange(): void
    {
        $webhook = $this->handler($this->validRequest('pay'), '203.0.113.55');
        $webhook->setAllowedIps(['203.0.113.0/24']);

        $this->assertTrue($webhook->checkHandlerRequest());
    }

    public function testCidrAllowlistRejectsAddressOutOfRange(): void
    {
        $webhook = $this->handler($this->validRequest('pay'), '203.0.114.1');
        $webhook->setAllowedIps(['203.0.113.0/24']);

        $this->expectException(UnitpayIpException::class);
        $webhook->checkHandlerRequest();
    }

    /** CIDR matching works for IPv6 too (binary comparison via inet_pton). */
    public function testCidrAllowlistMatchesIpv6InRange(): void
    {
        $webhook = $this->handler($this->validRequest('pay'), '2001:db8::1');
        $webhook->setAllowedIps(['2001:db8::/32']);

        $this->assertTrue($webhook->checkHandlerRequest());
    }

    /** Before the first successful verification, the verified-data getters return null. */
    public function testHandlerGettersAreNullBeforeVerification(): void
    {
        $webhook = $this->handler($this->validRequest('pay'));

        $this->assertNull($webhook->getHandlerMethod());
        $this->assertNull($webhook->getHandlerParams());
    }

    /** After a successful verification, getHandlerParams() returns exactly the verified webhook params. */
    public function testGetHandlerParamsReturnsVerifiedParams(): void
    {
        $request = $this->validRequest('pay');
        $webhook = $this->handler($request);

        $this->assertTrue($webhook->checkHandlerRequest());
        $this->assertSame($request['params'], $webhook->getHandlerParams());
        $this->assertSame('42', $webhook->getHandlerParams()['account']);
    }

    /** A typed exception that still extends the historical SPL type + the marker interface. */
    public function testSignatureFailureThrowsTypedExceptionStillCatchableAsInvalidArgument(): void
    {
        $request = $this->validRequest('pay');
        $request['params']['orderSum'] = '0.01'; // changed after signing

        try {
            $this->handler($request)->checkHandlerRequest();
            $this->fail('expected a signature exception');
        } catch (UnitpaySignatureException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertInstanceOf(UnitpayExceptionInterface::class, $e);
        }
    }
}
