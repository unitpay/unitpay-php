# v3 Migration Guide (2.x → 3.0)

[Back to README](../README.md) · [Getting Started →](getting-started.md)

3.0 replaces the single-file, global-namespace SDK with a PSR-4 package under `src/`,
organized into layers behind a thin `Unitpay\Unitpay` facade. Nothing about the wire
protocol changed — only the PHP surface you call.

**There is no compatibility shim.** A `class_alias` would have given you the old class
names without the old methods or constants, which is worse than a clean break: the code
would compile and then fail at runtime. Migrating is a mechanical, one-time edit, and this
guide lists every rename.

## What did NOT change

Before you start, the reassuring part — none of this needs touching:

* The signature algorithm, the `{up}` delimiter, and the `PHP_INT_MAX` guard.
* The request format on the wire (flat `method=X&...` query params).
* Webhook verification semantics: signature **and** `REMOTE_ADDR` allowlist, `hash_equals`,
  the fail-safe IP-feed refresh.
* Response shapes — you still get the decoded JSON envelope as `object`.
* Exception class names and their SPL parents, so existing `catch` blocks keep working
  once the `use` statements are updated.
* PHP >= 7.4 support and the zero-dependency policy.

## 1. Installation

The SDK is no longer a file you can `require` directly — it needs the PSR-4 autoloader:

```diff
-require '/path/to/UnitPay.php';
+require __DIR__ . '/vendor/autoload.php';
```

If you installed by downloading the repository, switch to Composer:

```sh
composer require unitpay/php-sdk:^3.0
```

## 2. Class names

| 2.x (global namespace) | 3.0 |
| --- | --- |
| `UnitPay` | `Unitpay\Unitpay` |
| `CashItem` | `Unitpay\Model\CashItem` |
| `UnitpayIpAllowlist` | `Unitpay\Webhook\IpAllowlist` |
| `UnitpayExceptionInterface` | `Unitpay\Exception\UnitpayExceptionInterface` |
| `UnitpaySignatureException` | `Unitpay\Exception\UnitpaySignatureException` |
| `UnitpayIpException` | `Unitpay\Exception\UnitpayIpException` |
| `UnitpayTransportException` | `Unitpay\Exception\UnitpayTransportException` |
| `UnitpayUnsupportedMethodException` | `Unitpay\Exception\UnitpayUnsupportedMethodException` |
| `UnitpayValidationException` | `Unitpay\Exception\UnitpayValidationException` |

Watch the casing: the class is `Unitpay`, not `UnitPay`.

```diff
+use Unitpay\Unitpay;
+
-$unitpay = new UnitPay('unitpay.ru', $secretKey);
+$unitpay = new Unitpay('unitpay.ru', $secretKey);
```

`form()` and the fluent setters (`setBackUrl`, `setCustomerEmail`, `setCustomerPhone`,
`setCashItems`) stayed on the facade with identical signatures — those call sites need no
change beyond the class name.

## 3. `api()` → service methods

`api('<method>', [...])` is gone. Each API method is now a typed method on one of four
service objects, with its required parameters as arguments and everything else in a
trailing options array.

### Payments — `$unitpay->payments()`

| 2.x | 3.0 |
| --- | --- |
| `api('initPayment', ['account' => $a, 'sum' => $s, 'projectId' => $p, 'paymentType' => $t])` | `initPayment($a, $s, $p, $t)` |
| `api('getPayment', ['paymentId' => $id])` | `getPayment($id)` |
| `api('refundPayment', ['paymentId' => $id])` | `refundPayment($id)` |
| `api('refundPayment', ['paymentId' => $id, 'sum' => $s])` | `refundPayment($id, ['sum' => $s])` |
| `api('confirmPayment', ['paymentId' => $id])` | `confirmPayment($id)` |
| `api('cancelPayment', ['paymentId' => $id])` | `cancelPayment($id)` |
| `api('offsetAdvance', ['login' => $l, 'paymentId' => $id])` | `offsetAdvance($l, $id)` |

Extra parameters — `desc`, `currency`, `backUrl`, ... — move into the options array:

```diff
-$response = $unitpay->api('initPayment', [
-    'account'     => $orderId,
-    'desc'        => $orderDesc,
-    'sum'         => $orderSum,
-    'paymentType' => UnitPay::PAYMENT_TYPE_CARD,
-    'currency'    => $orderCurrency,
-    'projectId'   => $projectId,
-]);
+$response = $unitpay->payments()->initPayment(
+    $orderId,
+    $orderSum,
+    $projectId,
+    PaymentType::CARD,
+    [
+        'desc'     => $orderDesc,
+        'currency' => $orderCurrency,
+    ]
+);
```

### Subscriptions — `$unitpay->subscriptions()`

| 2.x | 3.0 |
| --- | --- |
| `api('listSubscriptions', ['projectId' => $p])` | `listSubscriptions($p)` |
| `api('listSubscriptions', ['projectId' => $p, 'all' => 1])` | `listSubscriptions($p, ['all' => 1])` |
| `api('getSubscription', ['subscriptionId' => $s])` | `getSubscription($s)` |
| `api('closeSubscription', ['subscriptionId' => $s])` | `closeSubscription($s)` |

### Payouts — `$unitpay->payouts()`

| 2.x | 3.0 |
| --- | --- |
| `api('massPayment', ['login' => $l, 'transactionId' => $tx, 'sum' => $s, 'purse' => $pu, 'paymentType' => $t])` | `massPayment($l, $tx, $s, $pu, $t)` |
| `api('massPaymentStatus', ['login' => $l, 'transactionId' => $tx])` | `massPaymentStatus($l, $tx)` |
| `api('massPaymentAvailableAmount', ['login' => $l, 'sum' => $s, 'purse' => $pu, 'paymentType' => $t])` | `massPaymentAvailableAmount($l, $s, $pu, $t)` |
| `api('massPaymentCommissions', ['login' => $l])` | `massPaymentCommissions($l)` |
| `api('getSbpBankList', ['login' => $l])` | `getSbpBankList($l)` |
| `api('getBinInfo', ['login' => $l, 'bin' => $b])` | `getBinInfo($l, $b)` |

### Reference — `$unitpay->reference()`

| 2.x | 3.0 |
| --- | --- |
| `api('getMethodsAvailable', ['projectId' => $p])` | `getMethodsAvailable($p)` |
| `api('getCommissions', ['projectId' => $p, 'login' => $l])` | `getCommissions($p, $l)` |
| `api('getCurrencyCourses', ['login' => $l])` | `getCurrencyCourses($l)` |
| `api('getPartner', ['login' => $l])` | `getPartner($l)` |

Note that **`getBinInfo` moved to `payouts()`**, not `reference()` — it sits next to the
SBP bank list, the other payout-routing lookup.

### Account-level calls

The account key still overrides the project key; it just travels in the options array now,
while the login became a proper argument:

```diff
-$response = $unitpay->api('getPartner', [
-    'login'     => 'partner@example.com',
-    'secretKey' => $accountKey,
-]);
+$response = $unitpay->reference()->getPartner('partner@example.com', [
+    'secretKey' => $accountKey,
+]);
```

## 4. Constants → `Model\Enum` const-classes

The dictionaries left `CashItem` and became const-classes under `Unitpay\Model\Enum`. The
prefix drops out, since the class name now carries it:

| 2.x | 3.0 | Rule |
| --- | --- | --- |
| `CashItem::NDS_NONE` | `Nds::NONE` | irregular — no `VAT` prefix |
| `CashItem::NDS_0`, `NDS_20`, `NDS_122` | `Nds::VAT0`, `Nds::VAT20`, `Nds::VAT122` | `NDS_<n>` → `VAT<n>` |
| `CashItem::PAYMENT_OBJECT_COMMODITY` | `PaymentObject::COMMODITY` | drop `PAYMENT_OBJECT_` |
| `CashItem::PAYMENT_METHOD_PAYMENT_FULL` | `PaymentMethod::PAYMENT_FULL` | drop `PAYMENT_METHOD_` |
| `CashItem::MEASURE_KG` | `Measure::KG` | drop `MEASURE_` |
| `UnitPay::PAYMENT_TYPE_CARD` | `PaymentType::CARD` | drop `PAYMENT_TYPE_` |
| `UnitPay::VERSION`, `UnitPay::API_VERSION` | `Unitpay::VERSION`, `Unitpay::API_VERSION` | stayed on the facade |

`Nds::NONE` is the one case the rule does not cover — `NDS_NONE` means "no VAT", not
"VAT of NONE", so it did not gain a `VAT` prefix.

```diff
+use Unitpay\Model\CashItem;
+use Unitpay\Model\Enum\Nds;
+use Unitpay\Model\Enum\PaymentMethod;
+use Unitpay\Model\Enum\PaymentObject;
+
 $item = new CashItem(
     'Iphone 6 Skin Cover',
     1,
     900,
-    CashItem::NDS_20,
-    CashItem::PAYMENT_OBJECT_COMMODITY,
-    CashItem::PAYMENT_METHOD_PAYMENT_FULL
+    Nds::VAT20,
+    PaymentObject::COMMODITY,
+    PaymentMethod::PAYMENT_FULL
 );
```

## 5. Webhooks moved behind `webhook()`

Everything about inbound verification and the IP allowlist now lives on
`Unitpay\Webhook\WebhookVerifier`, reached via `$unitpay->webhook()`:

| 2.x — on `UnitPay` | 3.0 — on `$unitpay->webhook()` |
| --- | --- |
| `checkHandlerRequest()` | `checkHandlerRequest()` |
| `getHandlerMethod()`, `getHandlerParams()` | same |
| `getSuccessHandlerResponse()`, `getErrorHandlerResponse()` | same |
| `setAllowedIps()`, `addAllowedIps()`, `getAllowedIps()`, `refreshAllowedIps()` | same |

```diff
-$unitpay = new UnitPay($domain, $secretKey);
-$unitpay->checkHandlerRequest();
-print $unitpay->getSuccessHandlerResponse('Pay Success');
+$webhook = (new Unitpay($domain, $secretKey))->webhook();
+$webhook->checkHandlerRequest();
+print $webhook->getSuccessHandlerResponse('Pay Success');
```

If you subclassed `UnitPay` to override `getIp()` or `isAllowedIp()` behind a proxy,
subclass `Unitpay\Webhook\WebhookVerifier` instead — both are still `protected`.

## 6. Signing is its own class

`getSignature()` is no longer a public method on the facade. Direct signing — normally
only needed in tests or bespoke integrations — moved to `Unitpay\Signature\SignatureBuilder`:

```diff
-$signature = $unitpay->getSignature($params, 'pay');
+$signature = (new SignatureBuilder())->build($params, $secretKey, 'pay');
```

`form()` and the services sign internally, so ordinary code never calls this.

## 7. Transport: callable → `TransportInterface`

The third constructor argument used to accept a `callable`. It now takes a
`Unitpay\Http\TransportInterface`:

```diff
-$unitpay = new UnitPay($domain, $key, function (string $url, array $headers = []) {
-    return file_get_contents($url);
-});
+final class MyTransport implements Unitpay\Http\TransportInterface
+{
+    public function send(string $url, array $headers = [])
+    {
+        return file_get_contents($url);
+    }
+}
+
+$unitpay = new Unitpay($domain, $key, new MyTransport());
```

Passing `null` (or omitting it) still gives you the default `CurlTransport`, unchanged in
behavior. The same instance now serves both the API calls and the webhook IP-feed fetch.

## 8. Required parameters are checked earlier

In 2.x a missing required parameter surfaced at runtime as a
`UnitpayValidationException`. In 3.0 the method signatures enforce it, so the same mistake
is an `ArgumentCountError` — and, more usefully, your IDE and static analyzer catch it
before the code ever runs. `UnitpayValidationException` still covers a missing secret key
and invalid `CashItem` input.

Likewise, `UnitpayUnsupportedMethodException` is no longer thrown for outbound calls —
there is no method-name string to get wrong. It survives only for inbound webhooks whose
`method` is not one of `check` / `pay` / `preauth` / `error`.

## Migration checklist

1. `composer require unitpay/php-sdk:^3.0`, replace any direct `require` of `UnitPay.php`.
2. Add `use` statements; rename `UnitPay` → `Unitpay`, `CashItem` → `Unitpay\Model\CashItem`.
3. Replace every `api('method', [...])` with the service call from section 3.
4. Replace the `CashItem::*` / `UnitPay::PAYMENT_TYPE_*` constants per section 4.
5. Route webhook calls through `webhook()`.
6. Replace any callable transport with a `TransportInterface` implementation.
7. Run your test suite — most breakage surfaces as "undefined method" at analysis time.

## See Also

* [Getting Started](getting-started.md) — the 3.0 flows in full
* [API Methods](api-methods.md) — the complete service reference
* [CHANGELOG](../CHANGELOG.md) — the full list of 3.0 changes
