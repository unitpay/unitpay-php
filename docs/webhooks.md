# Webhooks (Payment Handler)

[← API Methods](api-methods.md) · [Back to README](../README.md) · [Telemetry →](telemetry.md)

The handler trusts a request only when the SHA-256 signature **and** the source IP both
match. Read the verified request from the SDK (`getHandlerMethod()` / `getHandlerParams()`)
rather than from `$_GET` directly.

```php
<?php

/**
 *  Demo handler for your projects
 *
 */
require __DIR__ . '/vendor/autoload.php';

// Project Data
$domain = 'unitpay.ru';// Your working domain: unitpay.ru or address provided by unitpay support service
$projectId  = 1;
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1';// Project secret key

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderCurrency  = 'RUB';

$unitpay = new UnitPay($domain, $secretKey);

try {
    // Validate request (check ip address, signature and etc)
    $unitpay->checkHandlerRequest();

    // Read the verified request from the SDK (honors the overridden request, not $_GET)
    $method = $unitpay->getHandlerMethod();
    $params = $unitpay->getHandlerParams();

    // Very important! Validate request with your order data, before complete order
    if (
        $params['orderSum'] != $orderSum ||
        $params['orderCurrency'] != $orderCurrency ||
        $params['account'] != $orderId ||
        $params['projectId'] != $projectId
    ) {
        // logging data and throw exception
        throw new InvalidArgumentException('Order validation Error!');
    }
    switch ($method) {
        // Just check order (check server status, check order in DB and etc)
        case 'check':
            echo $unitpay->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
        // Method Pay means that the money received
        case 'pay':
            // Please complete order
            echo $unitpay->getSuccessHandlerResponse('Pay Success');
            break;
        // Method Preauth means a two-stage hold: funds are only HELD, not captured yet.
        case 'preauth':
            // Do NOT deliver goods/services here; wait for 'pay'. Just acknowledge receipt.
            echo $unitpay->getSuccessHandlerResponse('Preauth received. Funds held, awaiting capture.');
            break;
        // Method Error means that an error has occurred.
        case 'error':
            // Please log error text.
            echo $unitpay->getSuccessHandlerResponse('Error logged');
            break;
        // Unknown method: do not leave an empty response (Unitpay would treat it as a failure).
        default:
            throw new InvalidArgumentException('Unexpected handler method: ' . $method);
    }
// Oops! Something went wrong.
} catch (Exception $e) {
    echo $unitpay->getErrorHandlerResponse($e->getMessage());
}
```

## Keeping the IP allowlist fresh

The built-in IP allowlist changes on Unitpay's side from time to time, so keep it fresh
from the published feed instead of waiting for a release:

* `$unitpay->refreshAllowedIps()` pulls the current list from
  `https://<domain>/ips/ips_webhooks.json`. It is fail-safe — on any network or parse
  error it keeps the built-in list and never throws. It makes a blocking HTTP request, so
  **don't call it on every webhook**: run it on a schedule (e.g. a daily cron), cache
  `getAllowedIps()`, and feed the cached list back with `setAllowedIps($cached)` in the
  handler.
* `$unitpay->addAllowedIps(['1.2.3.4', ...])` adds your own IPs (e.g. a proxy or relay) on
  top of the Unitpay list; they persist across `refreshAllowedIps()`.
* `$unitpay->setAllowedIps([...])` replaces the Unitpay list outright.
* Override `getIp()` if you run behind a proxy (the check uses `REMOTE_ADDR`, not the
  spoofable `X-Forwarded-For`).

```php
// Cron: refresh once, cache the result on your side.
$ips = (new UnitPay($domain, $secretKey))->refreshAllowedIps()->getAllowedIps();
cache_set('unitpay_ips', $ips);

// Handler: feed the cached list, no network call per callback.
(new UnitPay($domain, $secretKey))
    ->setAllowedIps(cache_get('unitpay_ips'))
    ->checkHandlerRequest();
```

## See Also

* [API Methods](api-methods.md) — the `api()` calls that trigger these callbacks
* [Getting Started](getting-started.md) — create the payments being confirmed here
