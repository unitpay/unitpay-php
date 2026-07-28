# Migrating to v4.0

[← Getting Started](getting-started.md) · [Back to README](../README.md) · [Migrating to v3 →](migration-v3.md)

Three things break in 4.0, and only one of them affects most integrations.

| Change | Affects you if… | Effort |
| --- | --- | --- |
| `TransportInterface` returns a `Response` | you wrote your own transport | ~10 lines |
| Stale webhooks are rejected | your handler runs more than 5 minutes behind, or your clock drifts | one call, or nothing |
| `X-Unitpay-Client` renamed to `Unitpay-Client` | something on your side reads the SDK's own outgoing headers | one rename |

Everything else is additive: configurable timeouts, retries for requests that never left
the client, and telemetry slots. If you use the SDK's own transport and your server clock
is synchronised, upgrading is a version bump.

Coming from 2.x? Do [the v3 migration](migration-v3.md) first — that one moved every class
into the `Unitpay\` namespace.

## 1. Custom transports: `send()` → `request()`

The old contract returned `string|false`, which discarded the HTTP status, the response
headers and the cURL error. That is why every failure used to arrive as the same
"Temporary server error", and it is why the SDK could not tell a safe retry from a
dangerous one.

```diff
 final class MyTransport implements Unitpay\Http\TransportInterface
 {
-    public function send(string $url, array $headers = [])
+    public function request(string $url, array $headers = []): Unitpay\Http\Response
     {
-        $body = my_http_get($url, $headers);
-
-        return $body === null ? false : $body;
+        $result = my_http_get($url, $headers);
+
+        if ($result === null) {
+            // No response. The third argument is the important one — see below.
+            return Unitpay\Http\Response::failed(0, 'my transport failed', false);
+        }
+
+        return Unitpay\Http\Response::received($result->status, $result->body, $result->headers);
     }
 }
```

`Response` is built through two named constructors, so a "failed" result cannot
accidentally carry an HTTP 200:

* `Response::received(int $status, string $body, array $headers = [])` — a response
  arrived, whatever its status.
* `Response::failed(int $errno, string $error, bool $requestSent)` — no response arrived.

### The failure arguments are a safety decision, not a detail

The retry policy reads them, and the Unitpay API accepts no idempotency key: repeating a
delivered `initPayment` can create a second payment. An attempt is repeated only on
positive knowledge that it never left the client.

`$requestSent` states whether the request had already gone out. Pass `true` when you know
the bytes went out — a read timeout, for example. Do not infer it from an error code: cURL
reports the same `CURLE_OPERATION_TIMEDOUT` for a connect timeout and a read timeout, and
`CurlTransport` has to consult `CURLINFO_CONNECT_TIME` to tell them apart.

**If you cannot tell, say so with the errno rather than the boolean:**

```php
// "Something failed and I cannot classify it." Never retried.
return Response::failed(Response::ERRNO_LOCAL, 'my transport cannot report why', false);

// "Nothing left this machine." Safe to retry.
return Response::failed(CURLE_COULDNT_CONNECT, 'connection refused', false);
```

`$requestSent = false` alone means *provably* not sent, which is a claim strong enough to
license a retry — it is not the place to express uncertainty. `ERRNO_LOCAL` is: it marks a
failure the transport could not classify, and the SDK never retries one. The SDK's own
`file_get_contents` fallback uses it for exactly this reason, since it sees no
connect/read phase.

Your transport should not throw. Describe the outcome in a `Response`; the SDK decides
which exception it deserves.

## 2. Failures are now typed

`UnitpayTransportException` is still thrown and still extends `InvalidArgumentException`,
so an existing `catch` keeps working. What changed is that it is now a base class with
three concrete cases underneath and accessors that carry the detail:

| Class | Raised when | Useful accessors |
| --- | --- | --- |
| `UnitpayNetworkException` | no response arrived — DNS, refused, timeout, or a local misconfiguration | `getErrno()`, `getTransportError()` |
| `UnitpayHttpException` | a response arrived with a non-2xx status | `getStatusCode()`, `getResponseBody()` |
| `UnitpayResponseException` | a 2xx arrived whose body is not a JSON object | `getStatusCode()`, `getResponseBody()` |

```php
use Unitpay\Exception\UnitpayHttpException;
use Unitpay\Exception\UnitpayNetworkException;
use Unitpay\Exception\UnitpayTransportException;

try {
    $response = $unitpay->payments()->getPayment($paymentId);
} catch (UnitpayHttpException $e) {
    // Unitpay answered and refused. The body is what support will ask you for.
    myLogger()->error('Unitpay HTTP ' . $e->getStatusCode(), ['body' => $e->getResponseBody()]);
} catch (UnitpayNetworkException $e) {
    // Nothing came back. The message says whether the request was actually sent.
    myLogger()->error('Unitpay unreachable: ' . $e->getMessage());
} catch (UnitpayTransportException $e) {
    // Still catches everything, including the malformed-payload case.
}
```

One message is gone deliberately: **"Temporary server error. Please try again later."** It
was also used for a disabled `allow_url_fopen`, which is permanent. If you match on that
string, match on the exception class instead.

Note what the examples above log: `getMessage()` and the typed accessors, never
`$e->getTrace()`. The secret key is a query parameter of this API, so it sits in the
arguments of the stack frames — harmless under PHP's default `zend.exception_ignore_args=1`,
and dumped verbatim by `getTrace()` when that is switched off. This is not new in 4.0, but
4.0 is the release that gives you something better to log; see
[Logging these exceptions without logging your key](api-methods.md#logging-these-exceptions-without-logging-your-key).

## 3. Webhooks: the replay window

`checkHandlerRequest()` now also checks that `params[date]` is within **300 seconds** of
your server clock, and throws `UnitpayReplayException` when it is not. Without it a
captured webhook stays replayable for as long as the secret key lives — the signature says
a request was genuine once, not that it is genuine now.

`UnitpayReplayException` extends `UnitpaySignatureException`, so a handler that already
rejects on a bad signature rejects a replay too, with no code change.

**Two things can break here, both worth fixing rather than tolerating:**

* **An unsynchronised server clock.** `date` is Moscow time (UTC+3) and the SDK parses it
  against that fixed offset, so your server's timezone does not matter — but its clock
  does. Run NTP.
* **A handler behind a slow queue.** If webhooks can sit for minutes before
  `checkHandlerRequest()` runs, verify on receipt rather than on processing.

If neither is fixable right now:

```php
$unitpay->webhook()->setWebhookTolerance(900); // 15 minutes
$unitpay->webhook()->setWebhookTolerance(0);   // off — replayable forever
```

A webhook that carries no `date` at all is accepted. It is inside the signed payload, so
an attacker cannot strip it to skip the check — removing any parameter breaks the
signature, which is verified first.

## 4. The client header was renamed

`X-Unitpay-Client` is now `Unitpay-Client`, and each telemetry slot inside it is an object
rather than a joined string:

```diff
-X-Unitpay-Client: {"sdk_version":"4.0.0", ..., "cms":"Bitrix/22.0"}
+Unitpay-Client: {"sdk_version":"4.0.0", ..., "cms":{"name":"Bitrix","version":"22.0"}}
```

The `X-` prefix was retired by [RFC 6648](https://www.rfc-editor.org/rfc/rfc6648) in 2012,
and the joined value forced a consumer to split on `/` — a separator a product name may
itself contain, `unitpay/woocommerce` being the obvious case.

This affects you only if something on your side reads the SDK's own outgoing headers: a
custom transport that logs them, or a test that asserts on them. The SDK sends one name,
not both — but note that 2.x and 3.x installs keep sending `X-Unitpay-Client`, so anything
parsing this header has to accept both names for as long as those versions are in the field.

See [Telemetry](telemetry.md) for the full payload and the slot rules.

## 5. What you get without doing anything

* **Retries.** The default transport now repeats a request that provably never reached
  Unitpay — DNS failure, refused connection, connect timeout — up to twice, with capped
  exponential backoff. It never repeats a read timeout, a 5xx, a 409 or a 429, because the
  server may already have acted on those. Turn it off with
  `DefaultTransport::withoutRetries()`.
* **Configurable timeouts.** `new CurlTransport($connectTimeout, $timeout)`, defaulting to
  the previous 5s/10s.
* **Telemetry slots.** `setCms()`, `setFramework()`, `setModule()` — see
  [Telemetry](telemetry.md). Worth filling in if you ship a CMS module.

## Checklist

1. Replace `send(): string|false` with `request(): Response` in any custom transport, and
   decide `$requestSent` deliberately — `false` when unsure.
2. Drop any string matching on `"Temporary server error"`.
3. Confirm your webhook host runs NTP, or call `setWebhookTolerance()`.
4. If anything on your side reads the SDK's outgoing headers, follow the
   `X-Unitpay-Client` → `Unitpay-Client` rename.
5. Optionally: set the telemetry slots, tune the timeouts, decide whether you want retries.

## See Also

* [Getting Started](getting-started.md) — construction, transports, error handling
* [Webhooks](webhooks.md) — the full inbound verification model
* [API Methods](api-methods.md) — the service surface and what each call throws
* [CHANGELOG](../CHANGELOG.md) — everything in 4.0.0, including the folded-in 3.1.0 batch
