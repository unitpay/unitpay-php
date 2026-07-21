<?php

namespace Tests;

use CashItem;
use InvalidArgumentException;
use UnexpectedValueException;
use UnitPay;
use PHPUnit\Framework\TestCase;

final class UnitPayApiTest extends TestCase
{
    public function testInitPaymentReturnsDecodedResponseViaInjectedTransport()
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

    public function testRequestUrlCarriesMethodParamsAndSecret()
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

    public function testRequestUrlUsesFlatParamsNotNested()
    {
        $captured = null;
        $transport = static function ($url) use (&$captured) {
            $captured = $url;
            return '{"result":{}}';
        };
        $unitPay = new UnitPay('unitpay.test', 'my-secret', $transport);

        $unitPay->api('getPayment', ['paymentId' => 555]);

        // Unitpay принимает плоские параметры строки запроса с 05/2026 — без устаревшей вложенности params[...].
        $this->assertStringContainsString('paymentId=555', $captured);
        $this->assertStringContainsString('secretKey=my-secret', $captured);
        $this->assertStringNotContainsString('params%5B', $captured);
        $this->assertStringNotContainsString('params[', $captured);
    }

    public function testPayoutRequestUrlUsesFlatParams()
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
     * Параметры, накопленные fluent-сеттерами (setCashItems/setCustomerEmail/…),
     * должны попадать в запрос api(), а не только в form(). Защита от регресса: раньше
     * api() строил URL только из аргумента $params и молча их терял.
     */
    public function testCashItemsFromSetterAreSentByApi()
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

    /** Явные параметры api() имеют приоритет над всем, что задано fluent-сеттерами. */
    public function testExplicitApiParamOverridesAccumulatedParam()
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
     * Параметры fluent-сеттеров очищаются успешным вызовом api() и не должны протекать
     * в следующий вызов на повторно используемом экземпляре (регресс: устаревший чек
     * cashItems или customerEmail иначе ушёл бы с несвязанным поздним заказом).
     */
    public function testFluentSetterParamsDoNotBleedIntoNextApiCall()
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

        // Второй вызов, без повторной установки чека/покупателя, должен быть чистым.
        $unitPay->api('getPayment', ['paymentId' => 555]);

        $this->assertStringContainsString('cashItems=', $urls[0]);
        $this->assertStringNotContainsString('cashItems=', $urls[1]);
        $this->assertStringNotContainsString('customerEmail=', $urls[1]);
    }

    /**
     * Параметры fluent-сеттеров очищаются только УСПЕШНЫМ вызовом api(). После сбоя
     * транспорта они сохраняются, чтобы повтор ушёл с тем же чеком, а не молча без него.
     */
    public function testFluentSetterParamsAreRetainedAfterFailedApiCall()
    {
        $urls = [];
        $calls = 0;
        // Первый вызов имитирует сбой транспорта (false), последующие — успех.
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
            // ожидаемо: транспорт вернул false
        }

        $unitPay->api('getPayment', ['paymentId' => 2]);

        // Чек уцелел после сбоя и ушёл с повтором.
        $this->assertStringContainsString('cashItems=', $urls[0]);
        $this->assertStringContainsString('cashItems=', $urls[1]);
    }

    public function testNonObjectResponseIsReportedAsTemporaryServerError()
    {
        $transport = static function () {
            return 'this is not json';
        };
        $unitPay = new UnitPay('unitpay.test', 'secret', $transport);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temporary server error');
        $unitPay->api('getPayment', ['paymentId' => 1]);
    }

    public function testUnsupportedMethodThrows()
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Method is not supported');
        $unitPay->api('doesNotExist');
    }

    public function testMissingRequiredParamThrows()
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return '{"result":{}}';
        });

        $this->expectException(InvalidArgumentException::class);
        // initPayment требует account, sum, projectId, paymentType
        $unitPay->api('initPayment', ['account' => 1]);
    }

    public function testMissingSecretThrows()
    {
        $unitPay = new UnitPay('unitpay.test', null, static function () {
            return '{"result":{}}';
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SecretKey is null');
        $unitPay->api('getPayment', ['paymentId' => 1]);
    }

    public function testPayoutMethodsAreSupportedAndValidateRequiredParams()
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
                // каждый метод выплат сначала требует login
                $this->assertStringContainsString('login', $e->getMessage());
            }
        }
    }

    /** Сбой транспорта — типизированное исключение, всё ещё перехватываемое как InvalidArgumentException. */
    public function testTransportFailureThrowsTypedTransportException()
    {
        $unitPay = new UnitPay('unitpay.test', 'secret', static function () {
            return false; // эмулируем сбой транспорта
        });

        try {
            $unitPay->api('getPayment', ['paymentId' => 1]);
            $this->fail('expected a transport exception');
        } catch (\UnitpayTransportException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertStringContainsString('Temporary server error', $e->getMessage());
        }
    }

    /** Неподдерживаемый метод бросает типизированное исключение, всё ещё перехватываемое как UnexpectedValueException. */
    public function testUnsupportedMethodThrowsTypedException()
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

    /** Методы уровня аккаунта могут переопределить ключ проекта ключом аккаунта (secretKey). */
    public function testExplicitSecretKeyOverridesInstanceKey()
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
