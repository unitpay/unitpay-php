<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayTelemetryTest extends TestCase
{
    /**
     * Transport spy: records the url/headers of each httpGet call.
     * @param array<int,array{url:string,headers:array<int,string>}> $calls
     */
    private function spy(array &$calls): callable
    {
        return static function (string $url, array $headers = []) use (&$calls): string {
            $calls[] = ['url' => $url, 'headers' => $headers];
            return '{"result":{}}';
        };
    }

    /**
     * @param array<int,string> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $h) {
            if (stripos($h, $name . ':') === 0) {
                return trim(substr($h, strlen($name) + 1));
            }
        }
        return null;
    }

    public function testApiSendsFingerprintHeaders(): void
    {
        $calls = [];
        $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls));
        $unitPay->api('getPayment', ['paymentId' => 1]);

        $headers = $calls[0]['headers'];
        $ua = $this->headerValue($headers, 'User-Agent');
        $client = $this->headerValue($headers, 'X-Unitpay-Client');

        $this->assertSame('unitpay-php-sdk/' . UnitPay::VERSION . ' php/' . PHP_VERSION, $ua);
        $decoded = json_decode((string) $client, true);
        $this->assertSame('php', $decoded['platform']);
        $this->assertSame(UnitPay::VERSION, $decoded['sdk_version']);
        $this->assertSame(PHP_VERSION, $decoded['php_version']);
    }
}
