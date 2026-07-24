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

That is the whole of it — there is no separate telemetry endpoint, no opt-in beacon, and
nothing to configure.

## See Also

* [Getting Started](getting-started.md) — the service and `form()` calls that carry the fingerprint
* [API Methods](api-methods.md) — the full service surface
