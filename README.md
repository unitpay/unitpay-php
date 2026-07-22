# Unitpay PHP SDK

[![CI](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![License](https://img.shields.io/packagist/l/unitpay/php-sdk.svg)](LICENSE.md)

PHP SDK for [Unitpay.ru](https://unitpay.ru).

Documentation: [help.unitpay.ru](https://help.unitpay.ru)

## Requirements

* PHP >= 7.4
* ext-json

No runtime dependencies. The whole SDK is a single file — [`UnitPay.php`](UnitPay.php) —
exposing two classes in the **global namespace**: `UnitPay` and `CashItem`.

## Examples

These are just some quick examples. The [`examples/`](examples) folder has
runnable samples for every method group:

* [`paymentForm.php`](examples/paymentForm.php) / [`initPaymentApi.php`](examples/initPaymentApi.php) — create a payment (form / API)
* [`receipt.php`](examples/receipt.php) — 54-FZ fiscal receipt via `CashItem`
* [`paymentInfo.php`](examples/paymentInfo.php) — `getPayment`
* [`webhook.php`](examples/webhook.php) — webhook handler (`check` / `pay` / `error`)
* [`refund.php`](examples/refund.php) — `refundPayment`
* [`twoStagePayment.php`](examples/twoStagePayment.php) — `confirmPayment` / `cancelPayment`
* [`subscriptions.php`](examples/subscriptions.php) — list / info / close subscriptions
* [`payout.php`](examples/payout.php) — payouts (mass-payment) + SBP bank list
* [`accountInfo.php`](examples/accountInfo.php) — balance, commissions, rates, BIN, methods
* [`offsetAdvance.php`](examples/offsetAdvance.php) — advance-offset fiscal receipt

### Payment integration using Unitpay form

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

### Fiscal receipts (54-FZ)

Attach receipt line items with `CashItem` and `setCashItems()` (works with both
`form()` and `api('initPayment', ...)`). The constructor takes the required
fields; optional fields are set via fluent setters and are serialized only when
set:

```php
$item = new CashItem(
    'Iphone 6 Skin Cover',              // name
    1,                                  // count
    900,                                // price
    CashItem::NDS_20,                   // VAT rate
    CashItem::PAYMENT_OBJECT_COMMODITY, // payment object
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);
$item->setMeasure(CashItem::MEASURE_ITEM);

$unitpay->setCashItems([$item]);
```

VAT rates (`NDS_*`), payment objects (`PAYMENT_OBJECT_*`), payment methods
(`PAYMENT_METHOD_*`) and units of measure (`MEASURE_*`) are exposed as constants
on `CashItem`.

> Since 2026 the backend fiscalizes `NDS_20` (`vat20`) as VAT **22%** — there is
> no separate path for "real" 20%. Pick the rate that matches the actual receipt
> (see [CHANGELOG.md](CHANGELOG.md)).

### Payment integration using Unitpay API

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

// My item Info
$itemName = 'Iphone 6 Skin Cover';

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "'.$itemName.'"';
$orderCurrency  = 'RUB';

$unitpay = new UnitPay($domain, $secretKey);

/**
 * Base params: account, desc, sum, currency, projectId, paymentType
 * paymentType — код способа оплаты из справочника (константы UnitPay::PAYMENT_TYPE_*):
 *   card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney.
 *
 * @link https://help.unitpay.ru/payments/create-payment
 * @link https://help.unitpay.ru/book-of-reference/payment-system-codes
 */
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
    // Url on PaymentGate
    $redirectUrl = $response->result->redirectUrl;
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // User redirect
    header("Location: " . $redirectUrl);

// If without redirect (invoice)
} elseif (isset($response->result->type)
    && $response->result->type === 'invoice') {
    // Url on receipt page in Unitpay
    $receiptUrl = $response->result->receiptUrl;
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // Invoice Id in Payment Gate (you can save it)
    $invoiceId = $response->result->invoiceId;
    // User redirect
    header("Location: " . $receiptUrl);

// If processed without redirect (e.g. recurring/subscription charge)
} elseif (isset($response->result->type)
    && $response->result->type === 'response') {
    // Payment ID in Unitpay (you can save it)
    $paymentId = $response->result->paymentId;
    // Human-readable result message
    $message = $response->result->message;
    // Optional status page in Unitpay: $response->result->statusUrl
    print $message;

// If error during api request
} elseif (isset($response->error->message)) {
    $error = $response->error->message;
    print 'Error: '.$error;
}
```

### Handler sample

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

// My item Info
$itemName = 'Iphone 6 Skin Cover';

// My Order Data
$orderId        = 'a183f94-1434-1e44';
$orderSum       = 900;
$orderDesc      = 'Payment for item "' . $itemName . '"';
$orderCurrency  = 'RUB';

$unitpay = new UnitPay($domain, $secretKey);

try {
    // Validate request (check ip address, signature and etc)
    $unitpay->checkHandlerRequest();

    list($method, $params) = [$_GET['method'], $_GET['params']];

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
        // Method Error means that an error has occurred.
        case 'error':
            // Please log error text.
            echo $unitpay->getSuccessHandlerResponse('Error logged');
            break;
    }
// Oops! Something went wrong.
} catch (Exception $e) {
    echo $unitpay->getErrorHandlerResponse($e->getMessage());
}
```

> The handler trusts a request only when the SHA-256 signature **and** the
> source IP both match. The built-in IP allowlist changes on Unitpay's side from
> time to time, so keep it fresh from the published feed instead of waiting for a
> release:
>
> * `$unitpay->refreshAllowedIps()` pulls the current list from
>   `https://<domain>/ips/ips_webhooks.json`. It is fail-safe — on any network or
>   parse error it keeps the built-in list and never throws. It makes a blocking
>   HTTP request, so **don't call it on every webhook**: run it on a schedule
>   (e.g. a daily cron), cache `getAllowedIps()`, and feed the cached list back
>   with `setAllowedIps($cached)` in the handler.
> * `$unitpay->addAllowedIps(['1.2.3.4', ...])` adds your own IPs (e.g. a proxy or
>   relay) on top of the Unitpay list; they persist across `refreshAllowedIps()`.
> * `$unitpay->setAllowedIps([...])` replaces the Unitpay list outright.
> * Override `getIp()` if you run behind a proxy.
>
> ```php
> // Cron: refresh once, cache the result on your side.
> $ips = (new UnitPay($domain, $secretKey))->refreshAllowedIps()->getAllowedIps();
> cache_set('unitpay_ips', $ips);
>
> // Handler: feed the cached list, no network call per callback.
> (new UnitPay($domain, $secretKey))
>     ->setAllowedIps(cache_get('unitpay_ips'))
>     ->checkHandlerRequest();
> ```

## API methods

All methods are called through `api('<method>', [...])`. `secretKey` is added
automatically from the constructor, so pass only the business params below.
Full parameters and response formats are in the
[official API documentation](https://help.unitpay.ru).

| Method | Required params | Purpose |
| --- | --- | --- |
| `initPayment` | `account`, `sum`, `projectId`, `paymentType` | Create a payment |
| `getPayment` | `paymentId` | Payment info |
| `refundPayment` | `paymentId` (+ optional `sum`) | Refund a payment (full or partial) |
| `confirmPayment` | `paymentId` | Confirm (capture) a two-stage payment |
| `cancelPayment` | `paymentId` | Cancel (release) a two-stage payment |
| `listSubscriptions` | `projectId` (+ optional `all`) | List project subscriptions |
| `getSubscription` | `subscriptionId` | Subscription info |
| `closeSubscription` | `subscriptionId` | Close a subscription |
| `getMethodsAvailable` | `projectId` | Payment methods available on the project |
| `getCommissions` | `projectId`, `login` | Acquiring commissions for a project |
| `getCurrencyCourses` | `login` | Currency conversion rates |
| `getPartner` | `login` | Account balance |
| `offsetAdvance` | `login`, `paymentId` (+ optional `cashItems`) | Advance-offset fiscal receipt |
| `massPayment` | `login`, `transactionId`, `sum`, `purse`, `paymentType` (+ `memberId` for SBP) | Create a payout |
| `massPaymentStatus` | `login`, `transactionId` | Payout status |
| `massPaymentAvailableAmount` | `login`, `sum`, `purse`, `paymentType` | Balance available for payout |
| `massPaymentCommissions` | `login` | Payout commissions |
| `getSbpBankList` | `login` | SBP participant banks |
| `getBinInfo` | `login`, `bin` | Card info by BIN |

For the account-level methods (`getCommissions`, `getCurrencyCourses`,
`getPartner`, `offsetAdvance` and all payout methods) the `secretKey` is the
**account** key (profile), not the project key, and `login` is the account email.
Pass the account key explicitly in the call — it overrides the constructor
(project) key:

```php
$response = $unitpay->api('getPartner', [
    'login'     => 'partner@example.com',
    'secretKey' => $accountKey, // overrides the project key from the constructor
]);
```

For SBP payouts pass `memberId` obtained from `getSbpBankList`.

Example — refund a payment:

```php
$response = $unitpay->api('refundPayment', [
    'paymentId' => 123456,
    // 'sum' => 100, // optional: partial refund
]);

if (isset($response->result->message)) {
    print $response->result->message;
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
```

Note: `confirmPayment` and `cancelPayment` return a top-level `message`
(`$response->message`), not `$response->result->message`.

## Installation

### Composer (recommended)

```sh
composer require unitpay/php-sdk
```

Then load the Composer autoloader — its classmap registers both `UnitPay` and
`CashItem`:

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

## Contributing

Please feel free to contribute to this project! Pull requests and feature requests welcome!
