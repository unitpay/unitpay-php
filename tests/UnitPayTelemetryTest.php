<?php

namespace Tests;

use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayTelemetryTest extends TestCase
{
    /**
     * Транспорт-шпион: записывает url/headers/timeoutMs каждого вызова httpGet.
     * @param array<int,array{url:string,headers:array<int,string>,timeoutMs:int|null}> $calls
     * @return callable
     */
    private function spy(&$calls)
    {
        return static function ($url, $headers = [], $timeoutMs = null) use (&$calls) {
            $calls[] = ['url' => $url, 'headers' => $headers, 'timeoutMs' => $timeoutMs];
            return '{"result":{}}';
        };
    }

    /**
     * @param array<int,string> $headers
     * @param string $name
     * @return string|null
     */
    private function headerValue(array $headers, $name)
    {
        foreach ($headers as $h) {
            if (stripos($h, $name . ':') === 0) {
                return trim(substr($h, strlen($name) + 1));
            }
        }
        return null;
    }

    public function testApiSendsFingerprintHeaders()
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
