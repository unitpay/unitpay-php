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

    public function testTelemetryDisabledByDefaultSendsNoBeacon(): void
    {
        $calls = [];
        $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls));
        // Пре-флайт ошибка (нет обязательных параметров) — реальный запрос не ушёл; телеметрия выключена.
        try {
            $unitPay->api('initPayment', ['account' => 1]);
        } catch (\Throwable $e) {
        }
        $this->assertSame([], $calls);
    }

    public function testEnabledTelemetryFiresBeaconWithFieldsAndShortTimeout(): void
    {
        $calls = [];
        $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls));
        $this->assertSame($unitPay, $unitPay->enableTelemetry());
        try {
            $unitPay->api('initPayment', ['account' => 1]);
        } catch (\Throwable $e) {
        }

        $this->assertCount(1, $calls);
        $this->assertStringStartsWith('https://unitpay.test/sdk/telemetry?', $calls[0]['url']);
        parse_str((string) parse_url($calls[0]['url'], PHP_URL_QUERY), $q);
        $this->assertSame(UnitPay::VERSION, $q['sdk']);
        $this->assertSame(PHP_VERSION, $q['php']);
        $this->assertSame('ERR_MISSING_REQUIRED_PARAM', $q['error']);
        $this->assertSame('initPayment', $q['method']);
        $this->assertSame(300, $calls[0]['timeoutMs']);
        // Граница анонимности: секрет не утекает в beacon.
        $this->assertStringNotContainsString('secret', $calls[0]['url']);
    }

    public function testEnvKillSwitchSuppressesBeacon(): void
    {
        putenv('UNITPAY_SDK_TELEMETRY_DISABLE=1');
        try {
            $calls = [];
            $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls));
            $unitPay->enableTelemetry();
            try {
                $unitPay->api('initPayment', ['account' => 1]);
            } catch (\Throwable $e) {
            }
            $this->assertSame([], $calls);
        } finally {
            putenv('UNITPAY_SDK_TELEMETRY_DISABLE');
        }
    }

    public function testWrongSignatureHandlerEmitsBeaconWithMethod(): void
    {
        $calls = [];
        $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls), [
            'method' => 'pay',
            'params' => ['signature' => 'bad'],
        ], '1.2.3.4');
        $unitPay->enableTelemetry();
        try {
            $unitPay->checkHandlerRequest();
        } catch (\Throwable $e) {
        }

        parse_str((string) parse_url($calls[0]['url'], PHP_URL_QUERY), $q);
        $this->assertSame('ERR_WRONG_SIGNATURE', $q['error']);
        $this->assertSame('pay', $q['method']);
    }

    public function testMissingMethodHandlerEmitsUnknownMethod(): void
    {
        $calls = [];
        $unitPay = new UnitPay('unitpay.test', 'secret', $this->spy($calls), [], '1.2.3.4');
        $unitPay->enableTelemetry();
        try {
            $unitPay->checkHandlerRequest();
        } catch (\Throwable $e) {
        }

        parse_str((string) parse_url($calls[0]['url'], PHP_URL_QUERY), $q);
        $this->assertSame('ERR_MISSING_METHOD', $q['error']);
        $this->assertSame('unknown', $q['method']);
    }

    public function testApiUnreachableEmitsNoBeacon(): void
    {
        $calls = [];
        // Транспорт фейлит реальный запрос (false); любой beacon тоже попал бы в $calls.
        $transport = static function ($url, $headers = [], $timeoutMs = null) use (&$calls) {
            $calls[] = $url;
            return false;
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $transport);
        $unitPay->enableTelemetry();
        try {
            $unitPay->api('getPayment', ['paymentId' => 1]);
        } catch (\Throwable $e) {
        }

        // Ровно один вызов — реальный api-запрос; beacon ERR_API_UNREACHABLE НЕ отправлен (тот же хост).
        $this->assertCount(1, $calls);
        $this->assertStringNotContainsString('/sdk/telemetry', $calls[0]);
    }

    public function testTelemetryFailureNeverPropagates(): void
    {
        $throwing = static function () {
            throw new \RuntimeException('beacon down');
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $throwing);
        $unitPay->enableTelemetry();
        // Пре-флайт ошибка + падающий beacon-транспорт: наружу выходит доменное исключение,
        // а не RuntimeException из телеметрии.
        $this->expectException(\UnitpayValidationException::class);
        $unitPay->api('initPayment', ['account' => 1]);
    }
}
