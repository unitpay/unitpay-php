# Unitpay PHP SDK

[![CI](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/unitpay/php-sdk/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/unitpay/php-sdk.svg)](https://packagist.org/packages/unitpay/php-sdk)
[![License](https://img.shields.io/packagist/l/unitpay/php-sdk.svg)](LICENSE.md)

> PHP SDK for the [Unitpay.ru](https://unitpay.ru) payment REST API.

A thin, stateless SDK: build a signed redirect to Unitpay's hosted payment page or call
the API server-to-server, attach 54-FZ fiscal receipts, and verify inbound webhooks.
Everything hangs off one entry point, `Unitpay\Unitpay`, which hands out service objects.

Official Unitpay documentation: [help.unitpay.ru](https://help.unitpay.ru)

> **Upgrading from 2.x?** 3.0 moves every class into the `Unitpay\` namespace and replaces
> `api('method', [...])` with typed service methods. There is no compatibility shim —
> see the [v3 Migration Guide](docs/migration-v3.md).

## Requirements

* PHP >= 7.4
* ext-json

No runtime dependencies. `ext-curl` is optional: the default transport uses it when
present and falls back to `file_get_contents()` otherwise.

## Installation

```sh
composer require unitpay/php-sdk
```

Then load the Composer autoloader — the package is PSR-4 (`Unitpay\` → `src/`):

```php
require __DIR__ . '/vendor/autoload.php';
```

See [Getting Started](docs/getting-started.md) for the `dev-master` option.

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Unitpay\Model\CashItem;
use Unitpay\Unitpay;

$unitpay = new Unitpay('unitpay.ru', $secretKey);

$unitpay
    ->setBackUrl('https://domain.com')
    ->setCustomerEmail('customer@domain.com')
    ->setCashItems([new CashItem('Iphone 6 Skin Cover', 1, 900)]);

$redirectUrl = $unitpay->form($publicId, 900, $orderId, 'Payment for item', 'RUB');

header('Location: ' . $redirectUrl);
```

Prefer a server-to-server call? Use `$unitpay->payments()->initPayment(...)` — see
[Getting Started](docs/getting-started.md).

## Key Features

* **Hosted form or API** — `form()` builds a signed redirect URL;
  `payments()->initPayment(...)` does a server-to-server call.
* **Service objects** — `payments()`, `subscriptions()`, `payouts()`, `reference()`, each
  with typed methods, so required parameters are enforced at the call site.
* **54-FZ fiscal receipts** — attach `CashItem` line items to any payment.
* **Secure webhooks** — `webhook()->checkHandlerRequest()` trusts a callback only when both
  the SHA-256 signature **and** the source-IP allowlist pass.
* **Dynamic IP allowlist** — refresh Unitpay's webhook IPs from the published feed,
  fail-safe.
* **Swappable transport** — inject any `Unitpay\Http\TransportInterface` to plug in your
  own HTTP stack or to test without the network.
* **Typed exceptions** — all implement `UnitpayExceptionInterface`.
* **Zero dependencies** — `ext-json` only (`ext-curl` optional).

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | Requirements, installation, first payment (form / API) |
| [Fiscal Receipts](docs/receipts.md) | 54-FZ receipt line items via `CashItem` |
| [API Methods](docs/api-methods.md) | Full service reference and account-level calls |
| [Webhooks](docs/webhooks.md) | Payment handler + keeping the IP allowlist fresh |
| [Telemetry](docs/telemetry.md) | Anonymous SDK version fingerprint |
| [v3 Migration Guide](docs/migration-v3.md) | Upgrading from 2.x to 3.0 |

Runnable samples for every method group live in [`examples/`](examples).

## Contributing

Please feel free to contribute to this project! Pull requests and feature requests
welcome!

## License

MIT — see [LICENSE.md](LICENSE.md).
