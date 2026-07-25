# Getting Started

[Back to README](../README.md) · [Fiscal Receipts →](receipts.md)

## Requirements

* PHP >= 7.4
* ext-json

No runtime dependencies. The SDK is a PSR-4 package: namespace `Unitpay\` maps to `src/`,
with `Unitpay\Unitpay` as the single entry point. `ext-curl` is optional — the default
transport uses it when present and falls back to `file_get_contents()` otherwise.

Upgrading from 2.x? Start with the [v3 Migration Guide](migration-v3.md).

## Installation

### Composer

```sh
composer require unitpay/php-sdk
```

Then load the Composer autoloader:

```php
require __DIR__ . '/vendor/autoload.php';
```

To follow the default branch (latest changes) instead of the newest tag:

```sh
composer require unitpay/php-sdk:dev-master
```

Composer is the only supported installation path since 3.0: the SDK is no longer a single
file you can `require` directly, so it needs a PSR-4 autoloader.

## The entry point

`Unitpay\Unitpay` is a thin facade. It builds the payment-form URL itself and hands out
service objects for everything else:

| Accessor | What it covers |
| --- | --- |
| `payments()` | `initPayment`, `getPayment`, `refundPayment`, `confirmPayment`, `cancelPayment`, `offsetAdvance` |
| `subscriptions()` | `listSubscriptions`, `getSubscription`, `closeSubscription` |
| `payouts()` | `massPayment*`, `getSbpBankList`, `getBinInfo` |
| `reference()` | `getMethodsAvailable`, `getCommissions`, `getCurrencyCourses`, `getPartner` |
| `webhook()` | Inbound verification and the IP allowlist |

The constructor takes the domain, the project secret key, and three optional seams —
a `TransportInterface`, the inbound request array, and the client IP:

```php
new Unitpay(string $domain, ?string $secretKey = null, ?TransportInterface $transport = null, ?array $request = null, ?string $clientIp = null)
```

`$domain` is a **bare host** — `unitpay.ru`, or the address Unitpay support gave you — with
an optional `:port`. No scheme, path or query: it is interpolated into the API endpoint, the
hosted-form URL and the webhook IP feed, so `https://unitpay.ru` or `unitpay.ru/api` would
produce broken URLs. Anything else throws `UnitpayValidationException` from the constructor.

### Transport, timeouts and retries

Leave `$transport` null and you get `DefaultTransport::create()`: cURL (falling back to
`file_get_contents` when `ext-curl` is missing) behind a retry policy. Override it to tune
either half:

```php
use Unitpay\Http\CurlTransport;
use Unitpay\Http\DefaultTransport;
use Unitpay\Http\RetryingTransport;

// Longer read timeout, still retried.
new Unitpay('unitpay.ru', $secretKey, new RetryingTransport(new CurlTransport(5, 30)));

// No retries — fail on the first attempt.
new Unitpay('unitpay.ru', $secretKey, DefaultTransport::withoutRetries());
```

Both timeouts are in seconds and must be positive; cURL treats 0 as "wait forever", so it
is rejected at construction rather than at the first request.

**What gets retried is narrow on purpose.** Only a failure that provably never left the
client — DNS failure, refused connection, connect-phase timeout — is repeated. A read
timeout, a 5xx, a 409 and a 429 are not, because Unitpay may already have acted on the
request and the API accepts no idempotency key to make a repeat harmless. The
`file_get_contents` fallback is never retried: it cannot tell the phases apart.

## Create a payment (Unitpay hosted form)

`form()` builds a signed redirect URL to Unitpay's hosted payment page. Fluent setters
(`setBackUrl`, `setCustomerEmail`, `setCustomerPhone`, `setCashItems`) are optional and
apply to `form()`, `payments()->initPayment()` and `payments()->offsetAdvance()` — see
[Fluent parameters](api-methods.md#fluent-parameters).

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Unitpay\Model\CashItem;
use Unitpay\Unitpay;

// Project Data
$domain     = 'unitpay.ru'; // Your working domain: unitpay.ru or the address Unitpay support gave you
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1'; // Project secret key
$publicId   = '15155-ae12d';

// My item Info
$itemName = 'Iphone 6 Skin Cover';

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "' . $itemName . '"';
$orderCurrency  = 'RUB';

$unitpay = new Unitpay($domain, $secretKey);

$unitpay
    ->setBackUrl('https://domain.com')
    ->setCustomerEmail('customer@domain.com')
    ->setCustomerPhone('79001235555')
    ->setCashItems([
       new CashItem($itemName, 1, $orderSum)
    ]);

$redirectUrl = $unitpay->form(
    $publicId,
    $orderSum,
    $orderId,
    $orderDesc,
    $orderCurrency
);

header("Location: " . $redirectUrl);
```

## Create a payment (Unitpay API)

`payments()->initPayment(...)` does a server-to-server call. `secretKey` is added
automatically from the constructor. The four required parameters are method arguments —
account, sum, projectId, paymentType — and anything else goes in the options array.
`paymentType` is a payment-method code from the reference (the `PaymentType` constants):
`card`, `cardInvoice`, `sbp`, `sberpay`, `tinkoffpay`, `paypal`, `webmoney` — see the
[payment-system codes](https://help.unitpay.ru/book-of-reference/payment-system-codes).

```php
<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * API integration
 *
 * @link https://help.unitpay.ru/payments/create-payment
 */

require __DIR__ . '/vendor/autoload.php';

use Unitpay\Model\Enum\PaymentType;
use Unitpay\Unitpay;

// Project Data
$domain     = 'unitpay.ru'; // Your working domain: unitpay.ru or the address Unitpay support gave you
$projectId  = 1;
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1'; // Project secret key

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "Iphone 6 Skin Cover"';
$orderCurrency  = 'RUB';

$unitpay = new Unitpay($domain, $secretKey);

$response = $unitpay->payments()->initPayment(
    $orderId,
    $orderSum,
    $projectId,
    PaymentType::CARD,
    [
        'desc'     => $orderDesc,
        'currency' => $orderCurrency,
    ]
);

// If need user redirect on Payment Gate
if (isset($response->result->type)
    && $response->result->type === 'redirect') {
    $redirectUrl = $response->result->redirectUrl;
    $paymentId = $response->result->paymentId; // Payment ID in Unitpay (you can save it)
    header("Location: " . $redirectUrl);

// If without redirect (invoice)
} elseif (isset($response->result->type)
    && $response->result->type === 'invoice') {
    $receiptUrl = $response->result->receiptUrl;
    $paymentId = $response->result->paymentId;
    $invoiceId = $response->result->invoiceId;
    header("Location: " . $receiptUrl);

// If processed without redirect (e.g. recurring/subscription charge)
} elseif (isset($response->result->type)
    && $response->result->type === 'response') {
    $paymentId = $response->result->paymentId;
    $message = $response->result->message; // Human-readable result message
    print $message;

// If error during api request
} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
```

## Testing without the network

The transport sits behind `Unitpay\Http\TransportInterface` — one method,
`request(string $url, array $headers = []): Response` — so you can inject a fake and
exercise the SDK without HTTP. The webhook verifier likewise accepts the inbound request
array and the client IP, instead of reading `$_GET` / `$_SERVER['REMOTE_ADDR']`:

```php
$unitpay = new Unitpay('unitpay.ru', $secretKey, $fakeTransport, $requestArray, '31.186.100.49');
```

## Runnable examples

The [`examples/`](../examples) folder has runnable samples for every method group (serve
them over HTTP, e.g. `php -S localhost:8000 -t examples`):

* [`paymentForm.php`](../examples/paymentForm.php) / [`initPaymentApi.php`](../examples/initPaymentApi.php) — create a payment (form / API)
* [`receipt.php`](../examples/receipt.php) — 54-FZ fiscal receipt via `CashItem`
* [`paymentInfo.php`](../examples/paymentInfo.php) — `getPayment`
* [`webhook.php`](../examples/webhook.php) — webhook handler (`check` / `pay` / `error`)
* [`refund.php`](../examples/refund.php) — `refundPayment`
* [`twoStagePayment.php`](../examples/twoStagePayment.php) — `confirmPayment` / `cancelPayment`
* [`subscriptions.php`](../examples/subscriptions.php) — list / info / close subscriptions
* [`payout.php`](../examples/payout.php) — payouts (mass-payment) + SBP bank list
* [`accountInfo.php`](../examples/accountInfo.php) — balance, commissions, rates, BIN, methods
* [`offsetAdvance.php`](../examples/offsetAdvance.php) — advance-offset fiscal receipt

## See Also

* [Fiscal Receipts](receipts.md) — attach 54-FZ receipt line items with `CashItem`
* [API Methods](api-methods.md) — the full service reference
* [Webhooks](webhooks.md) — verify inbound payment callbacks
* [v3 Migration Guide](migration-v3.md) — upgrading from 2.x
