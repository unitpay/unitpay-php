# Webhooks (Payment Handler)

[← API Methods](api-methods.md) · [Back to README](../README.md) · [Telemetry →](telemetry.md)

Inbound verification lives on `$unitpay->webhook()`, which returns a
`Unitpay\Webhook\WebhookVerifier`. The handler trusts a request only when the SHA-256
signature **and** the source IP both match. Read the verified request from the SDK
(`getHandlerMethod()` / `getHandlerParams()`) rather than from `$_GET` directly.

```php
<?php

/**
 *  Demo handler for your projects
 *
 */
require __DIR__ . '/vendor/autoload.php';

use Unitpay\Unitpay;

// Project Data
$domain     = 'unitpay.ru'; // Your working domain: unitpay.ru or the address Unitpay support gave you
$projectId  = 1;
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1'; // Project secret key

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderCurrency  = 'RUB';

$webhook = (new Unitpay($domain, $secretKey))->webhook();

try {
    // Validate request (check ip address, signature and etc)
    $webhook->checkHandlerRequest();

    // Read the verified request from the SDK (honors the overridden request, not $_GET)
    $method = $webhook->getHandlerMethod();
    $params = $webhook->getHandlerParams();

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
            echo $webhook->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
        // Method Pay means that the money received
        case 'pay':
            // Please complete order
            echo $webhook->getSuccessHandlerResponse('Pay Success');
            break;
        // Method Preauth means a two-stage hold: funds are only HELD, not captured yet.
        case 'preauth':
            // Do NOT deliver goods/services here; wait for 'pay'. Just acknowledge receipt.
            echo $webhook->getSuccessHandlerResponse('Preauth received. Funds held, awaiting capture.');
            break;
        // Method Error means that an error has occurred.
        case 'error':
            // Please log error text.
            echo $webhook->getSuccessHandlerResponse('Error logged');
            break;
        // Unknown method: do not leave an empty response (Unitpay would treat it as a failure).
        default:
            throw new InvalidArgumentException('Unexpected handler method: ' . $method);
    }
// Oops! Something went wrong.
} catch (Exception $e) {
    echo $webhook->getErrorHandlerResponse($e->getMessage());
}
```

## Keeping the IP allowlist fresh

The built-in IP allowlist changes on Unitpay's side from time to time, so keep it fresh
from the published feed instead of waiting for a release. All four calls live on the
webhook verifier:

* `$webhook->refreshAllowedIps()` pulls the current list from
  `https://<domain>/ips/ips_webhooks.json`. It is fail-safe — on any network or parse
  error it keeps the built-in list and never throws. It makes a blocking HTTP request, so
  **don't call it on every webhook**: run it on a schedule (e.g. a daily cron), cache
  `getAllowedIps()`, and feed the cached list back with `setAllowedIps($cached)` in the
  handler.
* `$webhook->addAllowedIps(['1.2.3.4', ...])` adds your own IPs (e.g. a proxy or relay) on
  top of the Unitpay list; they persist across `refreshAllowedIps()`.
* `$webhook->setAllowedIps([...])` replaces the Unitpay list outright. Passing an empty
  array is fail-closed, not a no-op: with no `addAllowedIps()` entries it rejects every
  webhook.
* Override `getIp()` if you run behind a proxy (the check uses `REMOTE_ADDR`, not the
  spoofable `X-Forwarded-For`). `getIp()` and `isAllowedIp()` are `protected`, so extend
  `WebhookVerifier` to change them.

```php
// Cron: refresh once, cache the result on your side.
$ips = (new Unitpay($domain, $secretKey))->webhook()->refreshAllowedIps()->getAllowedIps();
cache_set('unitpay_ips', $ips);

// Handler: feed the cached list, no network call per callback.
(new Unitpay($domain, $secretKey))
    ->webhook()
    ->setAllowedIps(cache_get('unitpay_ips'))
    ->checkHandlerRequest();
```

## See Also

* [API Methods](api-methods.md) — the service calls that trigger these callbacks
* [Getting Started](getting-started.md) — create the payments being confirmed here
