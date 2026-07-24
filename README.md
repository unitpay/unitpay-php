# Unitpay PHP SDK

[![CI](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![License](https://img.shields.io/packagist/l/unitpay/php-sdk.svg)](LICENSE.md)

> PHP SDK for the [Unitpay.ru](https://unitpay.ru) payment REST API.

A thin, stateless SDK: build a signed redirect to Unitpay's hosted payment page or call
the API server-to-server, attach 54-FZ fiscal receipts, and verify inbound webhooks. The
whole library is a single file in the **global namespace**.

Official Unitpay documentation: [help.unitpay.ru](https://help.unitpay.ru)

## Requirements

* PHP >= 7.4
* ext-json

No runtime dependencies. The SDK is a single file — [`UnitPay.php`](UnitPay.php) —
exposing two classes in the **global namespace**: `UnitPay` and `CashItem`.

## Installation

```sh
composer require unitpay/php-sdk
```

Then load the Composer autoloader — its classmap registers both `UnitPay` and `CashItem`:

```php
require __DIR__ . '/vendor/autoload.php';
```

See [Getting Started](docs/getting-started.md) for the `dev-master` and direct-download
options.

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$unitpay = new UnitPay('unitpay.ru', $secretKey);

$unitpay
    ->setBackUrl('https://domain.com')
    ->setCustomerEmail('customer@domain.com')
    ->setCashItems([new CashItem('Iphone 6 Skin Cover', 1, 900)]);

$redirectUrl = $unitpay->form($publicId, 900, $orderId, 'Payment for item', 'RUB');

header('Location: ' . $redirectUrl);
```

Prefer a server-to-server call? Use `$unitpay->api('initPayment', [...])` — see
[Getting Started](docs/getting-started.md).

## Key Features

* **Hosted form or API** — `form()` builds a signed redirect URL; `api('initPayment', ...)`
  does a server-to-server call.
* **54-FZ fiscal receipts** — attach `CashItem` line items to any payment.
* **Secure webhooks** — `checkHandlerRequest()` trusts a callback only when both the
  SHA-256 signature **and** the source-IP allowlist pass.
* **Dynamic IP allowlist** — refresh Unitpay's webhook IPs from the published feed,
  fail-safe.
* **Typed exceptions** — all implement `UnitpayExceptionInterface`.
* **Zero dependencies** — one file, `ext-json` only (`ext-curl` optional).

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | Requirements, installation, first payment (form / API) |
| [Fiscal Receipts](docs/receipts.md) | 54-FZ receipt line items via `CashItem` |
| [API Methods](docs/api-methods.md) | Full `api()` method reference and account-level calls |
| [Webhooks](docs/webhooks.md) | Payment handler + keeping the IP allowlist fresh |
| [Telemetry](docs/telemetry.md) | Anonymous SDK version fingerprint |

Runnable samples for every method group live in [`examples/`](examples).

## Contributing

Please feel free to contribute to this project! Pull requests and feature requests
welcome!

## License

MIT — see [LICENSE.md](LICENSE.md).
