# Getting Started

[Back to README](../README.md) · [Fiscal Receipts →](receipts.md)

## Requirements

* PHP >= 7.4
* ext-json

No runtime dependencies. The whole SDK is a single file — [`UnitPay.php`](../UnitPay.php) —
exposing two classes in the **global namespace**: `UnitPay` and `CashItem`. `ext-curl` is
optional: `api()` uses it when present and falls back to `file_get_contents()` otherwise.

## Installation

### Composer (recommended)

```sh
composer require unitpay/php-sdk
```

Then load the Composer autoloader — its classmap registers both `UnitPay` and `CashItem`:

```php
require __DIR__ . '/vendor/autoload.php';
```

To follow the default branch (latest changes) instead of the newest tag:

```sh
composer require unitpay/php-sdk:dev-master
```

### Direct download

Download the [latest version](https://github.com/unitpay/php-sdk/archive/master.zip),
unzip it and `require` the single file directly:

```php
require '/path/to/UnitPay.php';
```

## Create a payment (Unitpay hosted form)

`form()` builds a signed redirect URL to Unitpay's hosted payment page. Fluent setters
(`setBackUrl`, `setCustomerEmail`, `setCustomerPhone`, `setCashItems`) are optional and
apply to both `form()` and `api('initPayment', ...)`.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

// Project Data
$domain = 'unitpay.ru';// Your working domain: unitpay.ru or address provided by unitpay support service
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1';// Project secret key
$publicId   = '15155-ae12d';

// My item Info
$itemName = 'Iphone 6 Skin Cover';

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "' . $itemName . '"';
$orderCurrency  = 'RUB';

$unitpay = new UnitPay($domain, $secretKey);

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

`api('initPayment', ...)` does a server-to-server call. `secretKey` is added
automatically from the constructor. `paymentType` is a payment-method code from the
reference (`UnitPay::PAYMENT_TYPE_*` constants): `card`, `cardInvoice`, `sbp`, `sberpay`,
`tinkoffpay`, `paypal`, `webmoney` — see the
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

// Project Data
$domain = 'unitpay.ru';// Your working domain: unitpay.ru or address provided by unitpay support service
$projectId  = 1;
$secretKey  = '9e977d0c0e1bc8f5cc9775a8cc8744f1';// Project secret key

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "Iphone 6 Skin Cover"';
$orderCurrency  = 'RUB';

$unitpay = new UnitPay($domain, $secretKey);

$response = $unitpay->api('initPayment', [
    'account'     => $orderId,
    'desc'        => $orderDesc,
    'sum'         => $orderSum,
    'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
    'currency'    => $orderCurrency,
    'projectId'   => $projectId
]);

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
* [API Methods](api-methods.md) — the full `api()` method reference
* [Webhooks](webhooks.md) — verify inbound payment callbacks
