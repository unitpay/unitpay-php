# Telemetry

[← Webhooks](webhooks.md) · [Back to README](../README.md)

The SDK adds a small, **anonymous** version fingerprint to the requests it already makes,
so Unitpay can see which SDK/PHP versions are in the field. This is standard SDK
self-identification, like any `User-Agent` — it makes **no extra network calls** and never
sends secrets, amounts, or customer data:

* Service calls (`payments()`, `subscriptions()`, `payouts()`, `reference()`) carry a
  `User-Agent: unitpay-php-sdk/<ver> api/<v>` header and a `Unitpay-Client` JSON header
  with `sdk_version`, `api_version` (the Unitpay API surface targeted), `lang`,
  `lang_version`, `platform` (coarse OS family only), `publisher`.
* `form()` URLs carry an `sdk=php_<ver>_<major.minor>` query parameter (outside the
  signature — it does not affect it).

The webhook IP-feed fetch is a plain GET and carries no fingerprint headers.

There is no separate telemetry endpoint and no beacon.

## Naming your integration

The fields above cannot tell a shipped module from a bare script. Two optional calls fix
that, and between them you never have to classify anything:

* **`setModule()`** — what *you* wrote: the module, plugin, template or application.
* **`setStack()`** — what it *runs on*, outermost host first.

```php
$unitpay->setModule('unitpay-woocommerce', '2.1')
    ->setStack(['WordPress' => '6.5', 'WooCommerce' => '8.2']);
```

Both appear in both headers:

```text
User-Agent: unitpay-php-sdk/4.0.0 api/v1 WordPress/6.5 WooCommerce/8.2 unitpay-woocommerce/2.1
Unitpay-Client: {"sdk_version":"4.0.0", ...,
  "module":{"name":"unitpay-woocommerce","version":"2.1"},
  "stack":[{"name":"WordPress","version":"6.5"},{"name":"WooCommerce","version":"8.2"}]}
```

In `Unitpay-Client` each entry is an object, so a consumer never has to guess where a name
ends and a version begins — a product name may legitimately contain a slash, and the most
natural module name to pass is the Composer package name (`unitpay/woocommerce`). The
`User-Agent` keeps the joined `Name/Version` form because that field cannot carry structure;
treat the JSON header as the machine-readable one.

Set both once, right after construction — they apply to every later service call, including
through service objects that were already created.

### The runtime is not part of the stack

PHP and the operating system are reported automatically in `lang_version` and `platform`. The
rule: **if the SDK can find it out itself, it is not stack.** So PHP never appears in a PHP
stack, but WooCommerce does.

### Worked examples

```php
// CMS plugin
->setModule('unitpay-bitrix', '3.1')
->setStack(['Bitrix' => SM_VERSION]);

// four layers — a solution on top of a CMS, your module on top of that
->setModule('unitpay-bitrix', '3.1')
->setStack(['Bitrix' => SM_VERSION, 'Aspro Optimus' => ASPRO_OPTIMUS_VERSION]);

// a fork of a CMS: report both, nothing is lost
->setModule('unitpay-opencart', '1.4')
->setStack(['OpenCart' => '3.0.3.8', 'ocStore' => '3.0.3.2']);

// no CMS
->setModule('acme-shop', config('app.release'))
->setStack(['Laravel' => app()->version()]);

// nothing underneath — a bare script, a bot, a webhook receiver
->setModule('acme-tilda-bridge', '1.4');
```

The last case is ordinary, not a gap: leave the stack unset rather than inventing a value to
fill it in. A version you made up is indistinguishable from a real one.

`setStack()` **replaces** the whole stack, so calling it twice is safe in a bootstrap that may
run more than once.

### Nothing here can break a request

These setters usually run in an integration's bootstrap, so none of them raises on the values
you pass:

* An unset module, or an empty stack, is omitted rather than sent empty.
* A blank name or version is ignored — the call leaves the value as it was. If nothing was set
  before, nothing is sent; if a value was already there, it stays. A CMS that stops exposing
  its version string costs you a field in a header, not a checkout. In a stack, only that one
  entry is dropped; its neighbours survive.
* Control characters are stripped, and each half is capped (64 bytes for a name, 32 for a
  version) so a stray value cannot bloat the header.
* At most **eight** stack entries are sent. Far above any real stack, and it keeps a runaway
  loop from building a header an intermediary may reject.
* A non-ASCII name (`1С-Битрикс`, `Аспро: Магазин`) rides in the JSON header only. A
  `User-Agent` is an ASCII field, and an absent token there is better than a mangled one; the
  JSON header carries the name losslessly.
* An associative array cannot hold a duplicate product name, and a list passed by mistake
  (`setStack(['WordPress'])`) is dropped rather than reported as a product named `0`.

**These are product names and versions you supply.** Nothing is collected from the
environment beyond what is already listed above, and nothing identifies a merchant, a
payment or a customer.

## If you ship a template

A template — a repository someone clones and edits, rather than a package they install —
behaves differently from a module, in three ways worth knowing:

* **The version freezes.** Whoever cloned at `1.0` reports `1.0` for as long as the code runs,
  because nobody re-clones a template. So a stale version here is not someone who failed to
  update; there is no update channel. It still tells you which template generation is live in
  production, which is worth knowing when you plan a breaking change.
* **The code is forked immediately.** After a week it is not your template any more.
* **The identification line is the one most likely to be renamed**, because it sits in code the
  merchant edits.

So write it to survive:

```php
// Template identity. Please do not rename — this is how we know which template
// generations are live and what to warn about before a breaking change.
// Shipping something of your own on top? Add it to the stack:
//   ->setStack(['acme-bot' => '2.3'])
$unitpay->setModule('unitpay-telegram-bot-template', '1.0');
```

That also gives whoever forked it a better option than overwriting your name: keep it, and add
their own line to the stack. You then see both the origin and what grew out of it.

## Turning it off

```php
$unitpay->disableTelemetry();
```

`Unitpay-Client` is then not sent at all. The `User-Agent` keeps `unitpay-php-sdk/<ver>`
— a request that identifies no library at all is materially harder for Unitpay support to
help with, so that much stays.

## See Also

* [Getting Started](getting-started.md) — the service and `form()` calls that carry the fingerprint
* [API Methods](api-methods.md) — the full service surface
