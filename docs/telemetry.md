# Telemetry

[← Webhooks](webhooks.md) · [Back to README](../README.md)

The SDK adds a small, **anonymous** version fingerprint to the requests it already makes,
so Unitpay can see which SDK/PHP versions are in the field. This is standard SDK
self-identification, like any `User-Agent` — it makes **no extra network calls** and never
sends secrets, amounts, or customer data:

* Service calls (`payments()`, `subscriptions()`, `payouts()`, `reference()`) carry a
  `User-Agent: unitpay-php-sdk/<ver> api/<v>` header and an `X-Unitpay-Client` JSON header
  with `sdk_version`, `api_version` (the Unitpay API surface targeted), `lang`,
  `lang_version`, `platform` (coarse OS family only), `publisher`.
* `form()` URLs carry an `sdk=php_<ver>_<major.minor>` query parameter (outside the
  signature — it does not affect it).

The webhook IP-feed fetch is a plain GET and carries no fingerprint headers.

There is no separate telemetry endpoint and no beacon.

## Naming your integration

Most Unitpay integrations are CMS modules, and the fields above cannot tell one from a
bare script. Three optional slots fix that:

```php
$unitpay->setCms('Bitrix', '22.0')
    ->setFramework('Laravel', '11.0')
    ->setModule('unitpay-bitrix', '3.1');
```

Each filled slot appears in both headers:

```text
User-Agent: unitpay-php-sdk/4.0.0 api/v1 Laravel/11.0 Bitrix/22.0 unitpay-bitrix/3.1
X-Unitpay-Client: {"sdk_version":"4.0.0", ..., "framework":"Laravel/11.0","cms":"Bitrix/22.0","module":"unitpay-bitrix/3.1"}
```

Set them once, right after construction — they apply to every later service call, including
through service objects that were already created. An unset slot is omitted rather than sent
empty, and a slot given a name without a version (or the reverse) throws
`UnitpayValidationException` instead of emitting a meaningless `Bitrix/` token.

**These are product names and versions you supply.** Nothing is collected from the
environment beyond what is already listed above, and nothing identifies a merchant, a
payment or a customer.

## Turning it off

```php
$unitpay->disableTelemetry();
```

`X-Unitpay-Client` is then not sent at all. The `User-Agent` keeps `unitpay-php-sdk/<ver>`
— a request that identifies no library at all is materially harder for Unitpay support to
help with, so that much stays.

## See Also

* [Getting Started](getting-started.md) — the service and `form()` calls that carry the fingerprint
* [API Methods](api-methods.md) — the full service surface
