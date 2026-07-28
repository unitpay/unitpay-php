# Changelog

### v4.0.0 — 2026-07-25

**Breaking release.** The transport contract changed: `Unitpay\Http\TransportInterface` now returns a `Response` object instead of `string|false`, and inbound webhooks older than the tolerance window are rejected. If you implement your own transport, see [docs/migration-v4.md](docs/migration-v4.md); if you only use the SDK's own transport, the upgrade is a version bump.

> **3.1.0 was never published.** The BC-safe tech-debt batch prepared under that number ships here instead, so this entry covers both it and the 4.0 work. Nothing was dropped.

**Breaking changes.**

* **`TransportInterface::send(): string|false` became `request(): Response`.** The old contract threw away the HTTP status, the response headers and the cURL error, so a connect failure, a read timeout, a 404, a 500 with an HTML body, a disabled `allow_url_fopen` and malformed JSON all reached the caller as the same `UnitpayTransportException('Temporary server error. Please try again later.')` — including the two that are permanent rather than temporary. `Unitpay\Http\Response` carries the status, body, headers, errno and a `wasRequestSent()` flag. Only custom transport implementations are affected; the before/after is in [docs/migration-v4.md](docs/migration-v4.md)
* **Transport failures are now typed.** `UnitpayTransportException` stays the base and still extends `InvalidArgumentException`, so existing catch blocks keep working, but it now has three subclasses that say what actually happened: `UnitpayNetworkException` (no response, with `getErrno()` / `getTransportError()`), `UnitpayHttpException` (non-2xx, with `getStatusCode()` / `getResponseBody()`) and `UnitpayResponseException` (2xx whose body is not a JSON object). The blanket "Temporary server error" message is gone — if you matched on it, match on the class
* **Inbound webhooks older than 300 seconds are rejected** with `UnitpayReplayException`. A signature proves a webhook was genuine once, not that it is genuine now: until this release a captured request replayed indefinitely. `params[date]` is parsed against a fixed **UTC+3** offset rather than with `strtotime()`, which would resolve it against the ambient `date.timezone` and make the same webhook pass on a Moscow server and fail on a UTC one. `UnitpayReplayException` extends `UnitpaySignatureException`, so a handler that already rejects a bad signature needs no change. Widen or disable the window with `webhook()->setWebhookTolerance()`. A webhook carrying no `date` is accepted: the field is inside the signed payload, so an attacker cannot strip it to skip the check

**New.**

* **Network retries, enabled by default.** `RetryingTransport` repeats an attempt only when it provably never reached Unitpay — DNS failure, refused connection, connect-phase timeout — up to twice with capped exponential backoff and jitter. It does **not** retry a read timeout, a 5xx, a 409 or a 429. That is narrower than what other payment SDKs do, and deliberately so: they can attach an idempotency key that makes a repeat harmless, and the Unitpay API accepts none, so repeating a delivered `initPayment` can create a second payment. Disable with `DefaultTransport::withoutRetries()`. The `file_get_contents` fallback is never retried — it cannot report which phase failed
* **Configurable transport timeouts:** `new CurlTransport($connectTimeout, $timeout)`, defaulting to the previous 5s and 10s. A non-positive value is rejected at construction, since cURL reads 0 as "wait forever"
* **Telemetry slots for the integrating product:** `setCms()`, `setFramework()` and `setModule()` add the CMS, framework and module name/version to `User-Agent` and `X-Unitpay-Client`. Most Unitpay integrations are CMS modules, and the fixed fingerprint could not tell one from a bare script. `disableTelemetry()` drops `X-Unitpay-Client` entirely while the `User-Agent` keeps naming the SDK. Values are product names and versions supplied by the integrator — still no PII and still no extra request
* **Documented how to log a failure without logging your secret key.** The API takes `secretKey` as a query parameter, so it sits in the arguments of the stack frames: `$e->getTrace()` returns it in full when `zend.exception_ignore_args` is switched off (PHP defaults it to `1`, where every channel is clean, and `getMessage()` is clean either way). This is not new — it is as old as the API contract — but 4.0 is the release that gives you typed accessors worth logging instead. See [docs/api-methods.md](docs/api-methods.md)

**Behavior changes carried over from the unreleased 3.1.0.** Three settings that used to fail silently now either bind correctly or say what is wrong. Nothing changed on the wire.

* **Fluent setter params bind to the calls that accept them, not to whichever call runs first.** `AbstractService::request()` used to drain the accumulated params on every method, so `setCashItems()` followed by any lookup sent the 54-FZ receipt with that lookup — where it is meaningless — and the payment created right after it carried no receipt at all. Nothing reported the loss. The params are now read and cleared only by `form()`, `payments()->initPayment()` and `payments()->offsetAdvance()`; every other service call leaves them untouched, so a receipt survives an intervening lookup and still reaches its payment. A consuming call still clears them even when the request fails, so a retry must re-apply the setters. If your code relied on a receipt reaching a non-payment method, it was already being discarded before the payment — the fix makes it arrive instead
* **`setAllowedIps()` and `addAllowedIps()` reject malformed entries** with `UnitpayValidationException` instead of accepting them. A typo such as `31.186.100.4 9` or a `/33` prefix produced an allowlist that matched nothing, so every webhook was refused with a bare `IP address Error` and no hint at the cause. The whole call is rejected rather than the offending entry alone, so the allowlist is never left half-configured. `refreshAllowedIps()` is unchanged and still fail-safe: it drops bad feed entries and never throws
* **The constructor `$domain` must be a bare host** (optionally with a port) and is validated at construction time, throwing `UnitpayValidationException` otherwise. It feeds three URLs — the API endpoint, the hosted form and the webhook IP feed — so a value carrying a scheme or a path (`https://unitpay.ru`, `unitpay.ru/api`) used to produce malformed URLs whose failure surfaced much later as a transport error or an allowlist that quietly failed to refresh
* QA: PHPStan raised from level 6 to **level 8**, with no baseline and no suppressions. The three findings in `src/` were real type smells — the transport result reached `json_decode` as `string|false`, `curl_exec()` was returned as `bool|string` against a `string|false` signature, and `makeService()` returned a four-way union that each service getter narrowed without proof. Behavior is unchanged in all three
* QA: the PHPMD ruleset was still the one written for the pre-3.0 single-file class, lifting `ExcessiveClassComplexity` to 60 and `NPathComplexity` to 300 and excluding five further rules. Measured against the current `src/`, none of those five fires and the class-complexity default is never reached, so they were dropped. What remains is only what actually fires, each named in a comment; the two thresholds `checkHandlerRequest()` needs now sit just above its measured values
* **`ext-ctype` is now declared** in `composer.json`. `IpAllowlist` has always called `ctype_digit()` unconditionally, and unlike `ext-json` — compiled in unconditionally since PHP 8.0 — `ctype` is a bundled extension a build can still drop with `--disable-ctype`, so the dependency was real and undeclared. This is a stricter install constraint, which is why it lands in a major rather than a patch. Still no Composer packages at runtime, and `ext-curl` stays in `suggest`: `CurlTransport` keeps its `file_get_contents()` fallback
* QA: a new `ComposerRequirementsTest` holds the manifest to the code. It scans `src/` with the tokenizer for calls into extensions a build can disable and asserts each one is declared in `require` (or in `suggest`, which is `ext-curl` and only `ext-curl`), and it asserts `require` contains nothing but `php` and `ext-*`. The zero-Composer-dependency stance and the extension list are now regressions a test catches, not conventions a reviewer has to remember
* QA: `examples/` is analysed by `composer check` and by CI, through the new `composer stan-examples` script and `phpstan-examples.neon`. `examples/` is the source of truth for usage samples but sat outside `phpstan.neon`, and `composer lint` only syntax-checks it, so a renamed method or a changed argument count there stayed invisible while the whole gate was green. It runs at level 2 — method existence is only checked on expressions from that level up, and the examples call everything through a variable, never through `$this`, so level 0 misses precisely the regressions this guard exists for. The single `variable.undefined` identifier is suppressed, because every example pulls its variables in through `require` of `config.php` / `order.php`, which PHPStan does not follow; nothing else is
* QA: both PHPStan scripts pin `memory_limit=512M`. PHP's 128M default aborts PHPStan's parallel worker, which used to make a bare `composer check` fail for an environment reason that looked like a code finding
* CI: added PHP **8.5** to the test matrix, which now covers the whole declared `>=7.4` range, and `composer audit` no longer swallows failures
* CI: a tagged release verifies that `Unitpay::VERSION` matches the tag before publishing anything — the telemetry tests assert against the constant itself, so a forgotten bump used to stay green and ship the wrong version in `User-Agent`, `X-Unitpay-Client` and the form's `sdk` parameter. Release tags are unprefixed (`3.1.0`), the form used since 2.0.0

### v3.0.0 — 2026-07-25

**Breaking release.** The SDK is no longer a single file in the global namespace: it is now a PSR-4 package (`Unitpay\` → `src/`) split into layers — Http, Api services, Signature, Webhook, Model/Enum, Exception — behind a thin `Unitpay\Unitpay` facade. Nothing changed on the wire; only the PHP surface you call. Step-by-step renames are in [docs/migration-v3.md](docs/migration-v3.md).

* **No compatibility shim is provided.** A `class_alias` would have restored the old class names without the old methods and constants, so the code would compile and then fail at runtime; a full compat layer would have re-created the god class this release removes. Migration is a mechanical one-time edit instead
* Installation is Composer-only: `UnitPay.php` is gone, so the package needs the PSR-4 autoloader and can no longer be `require`d as a single file
* Classes moved: `UnitPay` → `Unitpay\Unitpay` (note the casing), `CashItem` → `Unitpay\Model\CashItem`, `UnitpayIpAllowlist` → `Unitpay\Webhook\IpAllowlist`, and every exception into `Unitpay\Exception\`. Exception class names and their `InvalidArgumentException` / `UnexpectedValueException` parents are unchanged, so existing catch blocks keep working once the imports are updated
* `api('method', [...])` was replaced by four service objects reached from the facade: `payments()`, `subscriptions()`, `payouts()`, `reference()`. Each method takes its required parameters as arguments and the rest in a trailing options array. `getBinInfo` sits on `payouts()`, next to `getSbpBankList`, rather than on `reference()`
* Required parameters are now enforced by the method signatures instead of the runtime `REQUIRED_UNITPAY_METHODS_PARAMS` dictionary — a missing one is an `ArgumentCountError` caught by static analysis, not a `UnitpayValidationException` at request time. `UnitpayUnsupportedMethodException` is no longer thrown for outbound calls (there is no method-name string to mistype) and now applies only to inbound webhooks
* The fiscal dictionaries left `CashItem` for const-classes in `Unitpay\Model\Enum`, dropping the redundant prefix: `CashItem::NDS_20` → `Nds::VAT20`, `CashItem::PAYMENT_OBJECT_COMMODITY` → `PaymentObject::COMMODITY`, `CashItem::PAYMENT_METHOD_PAYMENT_FULL` → `PaymentMethod::PAYMENT_FULL`, `CashItem::MEASURE_KG` → `Measure::KG`, `UnitPay::PAYMENT_TYPE_CARD` → `PaymentType::CARD`. One irregular case: `CashItem::NDS_NONE` → `Nds::NONE`, without a `VAT` prefix
* Inbound webhook verification and the IP allowlist moved to `Unitpay\Webhook\WebhookVerifier`, reached via `$unitpay->webhook()`: `checkHandlerRequest()`, `getHandlerMethod()`, `getHandlerParams()`, `getSuccessHandlerResponse()`, `getErrorHandlerResponse()`, `setAllowedIps()`, `addAllowedIps()`, `getAllowedIps()`, `refreshAllowedIps()`. `getIp()` and `isAllowedIp()` remain `protected` — subclass the verifier instead of the facade to run behind a proxy
* `getSignature()` is no longer public on the facade; direct signing lives in `Unitpay\Signature\SignatureBuilder::build($params, $secretKey, $method)`
* The transport constructor argument changed from a `callable` to `Unitpay\Http\TransportInterface`. Omitting it still yields the default `CurlTransport` with unchanged behavior (cURL with a `file_get_contents()` fallback, TLS verified). One transport instance now serves both the API calls and the webhook IP-feed fetch, so a custom HTTP stack is injected in a single place
* Deprecated payment objects rejected by the public API — `excise`, `gambling_bet`, `gambling_prize`, `lottery_prize`, `composite` — were announced in v2.1.0 for removal in 3.0, but are **kept in this release** and slated for 4.0 instead. Removing them alongside the namespace break would have added migration friction for no functional gain; they remain available as `PaymentObject::EXCISE` and friends, and should not be used in new code
* Unchanged and verified as such: the signature algorithm and the `{up}` delimiter, the mandatory `PHP_INT_MAX` guard against signature forgery, constant-time comparison via `hash_equals`, the `REMOTE_ADDR`-only IP source, TLS verification on the IP-feed fetch, the fail-safe allowlist refresh, the flat request format, response shapes, PHP >= 7.4 support and the zero-dependency policy
* Test suite ported to the new namespaces and grown from 121 to 150 tests: it now mirrors `src/`, uses a `TransportInterface` double instead of a callable, and covers the new seams (service getters and their memoization, shared transport injection)

### v2.1.0 — 2026-07-24

* Telemetry: passive anonymous version fingerprint (`User-Agent` and `X-Unitpay-Client` headers in `api()`, `sdk` parameter in the `form()` URL) — SDK self-identification with no extra network requests and no PII; there is no dedicated telemetry endpoint. `User-Agent: unitpay-php-sdk/<ver> api/<v>` and a JSON `X-Unitpay-Client` header with fields `sdk_version`, `api_version` (the Unitpay API version the SDK targets), `lang`, `lang_version`, `platform` (OS family only), `publisher`. Added constants `UnitPay::VERSION` and `UnitPay::API_VERSION`
* `CashItem`: 54-FZ dictionaries synced with the backend:
  * Added VAT rates: vat5, vat7, vat22 and the calculated vat105, vat107, vat110, vat120, vat122
  * Added payment objects: payment_2, deposit, expense, pension_insurance_ip, pension_insurance, medical_insurance_ip, medical_insurance, social_insurance, casino_payment, issuance_bank, commodity_without_mark, commodity_mark
  * Marked as deprecated (kept for backward compatibility, removal in 3.0) the values rejected by the public API: excise, gambling_bet, gambling_prize, lottery_prize, composite
* `CashItem`: added optional backend fields — sum, currency, measure (`MEASURE_*` constants), nomenclatureCode, markCode, markQuantity, pre_text, post_text; `setCashItems()` serializes them only when they are set
* `api()`: added support for the methods refundPayment, confirmPayment, cancelPayment, listSubscriptions, getSubscription, closeSubscription, getMethodsAvailable, getCommissions, getCurrencyCourses, getPartner, offsetAdvance — each with validation of its required parameters (secretKey is supplied automatically)
* `api()`: added mass-payout methods — massPayment, massPaymentStatus, massPaymentAvailableAmount, massPaymentCommissions, getSbpBankList, getBinInfo (require the account login and secretKey)
* `api()`: added `PAYMENT_TYPE_*` constants for the current Unitpay payment methods (card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney) — for convenience and typo protection only; `paymentType` is still passed without validation; in the README and the `initPaymentApi` example they replaced the deprecated qiwi/yandex/mc/alfaClick
* `api()`: an explicit secretKey in the call parameters overrides the key from the constructor — account-level and payout methods can be called with the account key instead of the project key
* `api()`: the required initPayment parameters were aligned with the backend — account, sum, projectId, paymentType (secretKey is validated separately); desc is no longer required
* `api()`: parameters are now sent flat (`method=X&account=…&secretKey=…`), as Unitpay documents and has accepted since 05.2026, instead of the deprecated `params[...]` nesting (still accepted by the backend, so this is not a breaking change); the inbound webhook handler is unaffected and keeps reading `params[...]`
* `api()`: parameters from the fluent setters (setCashItems, setCustomerEmail, setCustomerPhone, setBackUrl) now reach `api()` calls too, not just `form()`; explicit `api()` parameters take precedence over the accumulated ones
* `CashItem`: the constructor now rejects non-numeric count and price (previously only 0/negative were caught) and normalizes numeric strings to int/float
* `CashItem`: the constructor rejects a non-positive count and a negative price (behavior change)
* `CashItem`: preserves a fractional count (weight/volume goods) instead of truncating to int
* handler: the IP allowlist was trimmed to the officially published addresses (31.186.100.49, 51.250.20.9); 127.0.0.1 is not trusted by default (behind a reverse proxy on the same host it would nullify the IP check) — add it via `setAllowedIps()` for local debugging; `setAllowedIps()` itself was added to override the list
* handler: `isAllowedIp()` now matches not only exact IPs but also CIDR subnets (IPv4/IPv6) — `setAllowedIps(['77.75.153.0/25'])` works
* handler: added `refreshAllowedIps()` — pulls the current webhook IP list from the public feed `/ips/ips_webhooks.json` and replaces the built-in one (a decommissioned IP drops off automatically); fail-safe: on any transport/parse/validation error it keeps the built-in list and does not throw, so it can be called before `checkHandlerRequest()`
* handler: added `addAllowedIps()` — adds the merchant's own IPs/CIDRs (e.g. your own proxy/relay) on top of the Unitpay list; unlike `setAllowedIps()`, they are preserved across `refreshAllowedIps()`/`setAllowedIps()`
* handler: added `getAllowedIps()` — returns the effective list (Unitpay + merchant IPs); cache it after `refreshAllowedIps()` and feed it back via `setAllowedIps()` to avoid hitting the network on every webhook
* `UnitpayIpAllowlist::isValidEntry()` validates every loaded IP/CIDR entry, so malformed JSON can never empty the list
* handler: `checkHandlerRequest()` now accepts the `preauth` webhook (a hold notification in two-stage payments, when funds are blocked but not yet captured) — it used to be rejected as an unsupported method, which prevented two-stage/subscription handlers from verifying it
* Added typed exceptions (UnitpaySignatureException, UnitpayIpException, UnitpayTransportException, UnitpayUnsupportedMethodException) with the UnitpayExceptionInterface; each still extends its former SPL class, so existing catch blocks keep working
* `api()`: optional cURL transport with connect/read timeouts and no dependency on allow_url_fopen (falls back to file_get_contents); ext-curl added to composer "suggest"
* `api()`: the cURL transport does not call curl_close() on PHP 8.0+ (there it is a deprecated no-op that raises E_DEPRECATED on PHP 8.5 on every API call); on PHP <8.0 the handle (resource) is closed explicitly via a PHP_VERSION_ID check
* examples: a full set of scenarios — payment form (`paymentForm`), API (`initPaymentApi`), 54-FZ receipt via `CashItem` (`receipt`), webhook (`webhook`), `getPayment` (`paymentInfo`), refund (`refund`), two-stage (`twoStagePayment`), subscriptions (`subscriptions`), SBP payouts (`payout`), account reference calls (`accountInfo`), advance-offset receipt (`offsetAdvance`); added an `examples/README.md` index
* examples: connection settings and order data separated — `config.php` (domain, project/account keys, login) and `order.php` (order data); secrets are read from the environment (`UNITPAY_SECRET_KEY`, `UNITPAY_LOGIN`, `UNITPAY_ACCOUNT_SECRET_KEY`) instead of being hardcoded
* examples: robustness — `require` via `__DIR__` (independent of the working directory), `exit` after `header('Location:')`, `api()`/`form()` calls wrapped in try/catch (`UnitpayExceptionInterface`); `webhook` returns `application/json` and no longer leaves an empty response for an unknown method (`default` in the switch)
* examples: removed the unreachable "refund" handler branch; added a "preauth" branch (hold notification — acknowledge receipt but do not deliver goods, that waits for "pay"); handling of the "response" reply type from initPayment (e.g. recurring/subscription charges without a redirect)
* Added a PHPUnit test suite and injectable seams (getIp / API transport) for testability
* Added QA tooling: phpstan, php-cs-fixer, phpmd and parallel-lint
* Native type declarations: parameters, return types and typed properties across all three classes (`CashItem`, `UnitpayIpAllowlist`, `UnitPay`) within the PHP 7.4 limits (no union types, `mixed`, or `declare(strict_types)`) — the public API and behavior are unchanged. Money and quantity parameters (`form()` `$sum`, `CashItem` `$count`/`$price`, and the `httpGet()` `string|false` return) are deliberately left untyped to preserve the previous "soft" scalar ergonomics; their types are still documented in PHPDoc. PHPStan raised from level 5 to level 6
* Minimum PHP version raised to 7.4
* Hardening from code review:
  * `api()`: folds in only the fluent-setter parameters (cashItems/customerEmail/customerPhone/backUrl), not the whole set — a reused instance no longer leaks the key `form()` parameters or the stale signature into an unrelated `api()` call
  * `api()`: the fluent-setter parameters are now cleared once the request has been attempted — on a transport failure too, not only on success — so a stale receipt/customer can no longer leak from a failed call into an unrelated later call on a reused instance (symmetric with `form()`); a retry after a failure must re-apply the setters
  * `setCashItems()`: throws on a json_encode failure (e.g. a product name that is not UTF-8) instead of silently attaching an empty 54-FZ receipt
  * `CashItem`: preserves a fractional count (weight/volume goods) instead of truncating to int
  * `form()`: throws on an empty secret instead of returning an unsigned URL — like `api()`/`checkHandlerRequest()`
  * `getSignature()`/`api()`/`form()`: format float parameters locale-independently, so a comma-separator locale on PHP <8.0 cannot corrupt the signature or the amount
  * `api()`: an empty explicit secretKey (e.g. a getenv() that did not resolve) falls back to the instance key instead of throwing
  * `httpGet()`: suppresses the file_get_contents warning so a URL containing the secret does not leak into the error log
  * `checkHandlerRequest()`: exposes the verified method/params via `getHandlerMethod()`/`getHandlerParams()`, so the consumer does not re-read $_GET
  * `getSignature()`: rejects a null/empty secret up front — as a public method it must not silently hash with an empty secret (the appended null would coerce to '' and drop out, yielding a plausible but secret-less signature); the normal `form()`/`checkHandlerRequest()` paths already guarded this, this is defense-in-depth for direct calls
  * docs: `setAllowedIps([])` is documented as fail-closed (an empty allowlist rejects every webhook rather than being a no-op); the `$transport` seam docblock now documents the `$headers` argument the transport actually receives
  * every SDK exception implements UnitpayExceptionInterface (UnitpayValidationException was added for the missing-parameter/secret/method cases)

### v2.0.6 — 2025-05-14

* Added a new supported Unitpay IP address
* Updated README.md

### v2.0.5 — 2022-02-04

* Updated the list of Unitpay IP addresses
* Updated documentation links
* Improved code quality and structure

### v2.0.4 — 2021-03-17

* Updated the `getSignature` method (2Garin)

### v2.0.3 — 2021-02-20

* Filtering of signature input parameters (removing the sign/signature fields before signing)

### v2.0.2 — 2020-08-31

* Added the nds, type and paymentMethod parameters to `CashItem`

### v2.0.1 — 2020-03-03

* Added domain selection in the examples

### v2.0.0 — 2020-03-03

* Added domain selection (configurable API domain)
* Updated the documentation URL

### v1.1.2 — 2018-06-15

* Fixed the array_merge exception ("Argument #1 is not an array") when no receipt is set

### v1.1.1 — 2018-02-08

* Added a LICENSE file
* Fixed the composer file

### v1.1.0 — 2017-08-01

* Added customerEmail, customerPhone and cashItems to the payment form

### v1.0.0 — 2017-04-10

* First public release of the Unitpay PHP SDK
* Switched to SHA-256 signatures for all methods (MD5 support removed)
* Added the getPayment API method and the orderInfo.php example
* secretKey became a required parameter for API calls
* billingCode renamed to paymentType
* statusUrl deprecated in favor of receiptUrl
* Added support for the partner handler method "error"
* Added an overridable `getIp()` method
