# API Methods

[← Fiscal Receipts](receipts.md) · [Back to README](../README.md) · [Webhooks →](webhooks.md)

Server-to-server calls go through the service objects the facade hands out. Each method
takes its required parameters as arguments and everything else in a trailing options
array. `secretKey` is added automatically from the constructor. Full parameters and
response formats are in the [official API documentation](https://help.unitpay.ru).

Every method returns the decoded JSON envelope as `object`, and throws a
`UnitpayTransportException` when no usable response comes back.

## `payments()`

| Method | Signature | Purpose |
| --- | --- | --- |
| `initPayment` | `(string $account, $sum, $projectId, string $paymentType, array $options = [])` | Create a payment |
| `getPayment` | `($paymentId, array $options = [])` | Payment info |
| `refundPayment` | `($paymentId, array $options = [])` — `$options['sum']` for partial | Refund a payment |
| `confirmPayment` | `($paymentId, array $options = [])` | Confirm (capture) a two-stage payment |
| `cancelPayment` | `($paymentId, array $options = [])` | Cancel (release) a two-stage payment |
| `offsetAdvance` | `(string $login, $paymentId, array $options = [])` | Advance-offset fiscal receipt (account-level) |

## `subscriptions()`

| Method | Signature | Purpose |
| --- | --- | --- |
| `listSubscriptions` | `($projectId, array $options = [])` — `$options['all']` widens the listing | List project subscriptions |
| `getSubscription` | `($subscriptionId, array $options = [])` | Subscription info |
| `closeSubscription` | `($subscriptionId, array $options = [])` | Close a subscription |

## `payouts()`

All payout methods are account-level: the account email is the `$login` argument.

| Method | Signature | Purpose |
| --- | --- | --- |
| `massPayment` | `(string $login, $transactionId, $sum, string $purse, string $paymentType, array $options = [])` | Create a payout |
| `massPaymentStatus` | `(string $login, $transactionId, array $options = [])` | Payout status |
| `massPaymentAvailableAmount` | `(string $login, $sum, string $purse, string $paymentType, array $options = [])` | Balance available for payout |
| `massPaymentCommissions` | `(string $login, array $options = [])` | Payout commissions |
| `getSbpBankList` | `(string $login, array $options = [])` | SBP participant banks |
| `getBinInfo` | `(string $login, $bin, array $options = [])` | Card info by BIN |

## `reference()`

| Method | Signature | Purpose |
| --- | --- | --- |
| `getMethodsAvailable` | `($projectId, array $options = [])` | Payment methods available on the project |
| `getCommissions` | `($projectId, string $login, array $options = [])` | Acquiring commissions for a project |
| `getCurrencyCourses` | `(string $login, array $options = [])` | Currency conversion rates |
| `getPartner` | `(string $login, array $options = [])` | Account balance |

## Account-level methods

For the account-level methods — everything on `payouts()`, plus `getCommissions`,
`getCurrencyCourses`, `getPartner` and `offsetAdvance` — the `secretKey` is the **account**
key (profile), not the project key, and `login` is the account email. Pass the account key
in the options array; it overrides the constructor (project) key:

```php
$response = $unitpay->reference()->getPartner('partner@example.com', [
    'secretKey' => $accountKey, // overrides the project key from the constructor
]);
```

For SBP payouts pass `memberId` obtained from `getSbpBankList`.

Note that `getBinInfo` lives on `payouts()` rather than `reference()` — it sits next to
the SBP bank list, which is the other payout-routing lookup.

## Example — refund a payment

```php
$response = $unitpay->payments()->refundPayment(123456);
// Partial refund: ->refundPayment(123456, ['sum' => 100])

if (isset($response->result->message)) {
    print $response->result->message;
} elseif (isset($response->error->message)) {
    print 'Error: ' . $response->error->message;
}
```

Note: `confirmPayment` and `cancelPayment` return a top-level `message`
(`$response->message`), not `$response->result->message`.

## Fluent parameters

Parameters accumulated by `setCashItems()`, `setCustomerEmail()`, `setCustomerPhone()` and
`setBackUrl()` are merged into the calls that accept them, and cleared afterwards — so a
reused instance never carries one order's receipt into the next. Explicit options take
precedence over accumulated ones. The clearing happens even when the request fails, so a
retry must re-apply the setters.

Only three calls consume them:

| Call | Consumes |
| --- | --- |
| `form()` | `backUrl`, `customerEmail`, `customerPhone`, `cashItems` |
| `payments()->initPayment()` | `backUrl`, `customerEmail`, `customerPhone`, `cashItems` |
| `payments()->offsetAdvance()` | `cashItems` |

Every other service method — `getPayment`, `refundPayment`, `confirmPayment`,
`cancelPayment` and everything on `subscriptions()`, `payouts()` and `reference()` — neither
receives nor clears them. A lookup issued between the setters and the payment therefore
leaves the receipt alone:

```php
$unitpay->setCashItems([$item]);

$unitpay->reference()->getPartner($login);          // no receipt attached, nothing consumed
$unitpay->payments()->initPayment(...);             // the receipt arrives here
```

> Before 3.1 every service call drained these params, so the lookup above sent the receipt
> with `getPartner` and the payment was created without one.

## See Also

* [Getting Started](getting-started.md) — the `initPayment` flow in full
* [Webhooks](webhooks.md) — handle the callbacks a payment triggers
* [v3 Migration Guide](migration-v3.md) — the old `api('method', [...])` mapping
