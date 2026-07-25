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
  handler. Since 4.0 the default transport also retries a connect failure twice, so the
  worst case is roughly `(1 + 2) × timeout` plus backoff — about 32 seconds with the
  default 10s timeout. Fine on a cron, unacceptable in a handler.
* `$webhook->addAllowedIps(['1.2.3.4', ...])` adds your own IPs (e.g. a proxy or relay) on
  top of the Unitpay list; they persist across `refreshAllowedIps()`.
* `$webhook->setAllowedIps([...])` replaces the Unitpay list outright. Passing an empty
  array is fail-closed, not a no-op: with no `addAllowedIps()` entries it rejects every
  webhook.
* Both setters accept exact IPv4/IPv6 addresses and CIDR ranges, and reject anything else
  with `UnitpayValidationException`. A malformed entry (`'31.186.100.4 9'`,
  `'31.186.100.0/33'`) would otherwise match nothing and every webhook would fail with a
  bare `IP address Error`. The whole call is rejected, so the allowlist is never left
  half-configured. `refreshAllowedIps()` keeps its fail-safe contract instead: it drops bad
  entries from the feed and never throws.
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

## Replay protection

A signature proves a webhook was genuine when it was sent, not that it is genuine now:
without a freshness check, anyone who captures one request can resend it forever.

`checkHandlerRequest()` therefore also verifies that `params[date]` is within **300
seconds** of your server clock, and throws `UnitpayReplayException` when it is not. That
exception extends `UnitpaySignatureException`, so a handler that already rejects on a bad
signature rejects a replay unchanged.

The order of checks is signature → freshness → source IP. Freshness runs after the
signature deliberately: an unsigned payload must not be usable to probe how far your clock
is off.

**Timezone.** Unitpay sends `date` as Moscow time (`Y-m-d H:i:s`, UTC+3). The SDK parses it
against that fixed offset, never with `strtotime()` — which would resolve it against the
server's `date.timezone` and reject every webhook on a UTC host, three hours being ten
times the window. Your server timezone does not matter; your server **clock** does, so run
NTP.

```php
$unitpay->webhook()->setWebhookTolerance(900); // 15 minutes, e.g. behind a slow queue
$unitpay->webhook()->setWebhookTolerance(0);   // off — a captured webhook replays forever
```

A webhook that carries no `date` is accepted rather than refused. The field is part of the
signed payload, so an attacker cannot strip it to skip the check: removing any parameter
changes the hash and the signature check, which runs first, rejects the request.

## See Also

* [API Methods](api-methods.md) — the service calls that trigger these callbacks
* [Getting Started](getting-started.md) — create the payments being confirmed here
