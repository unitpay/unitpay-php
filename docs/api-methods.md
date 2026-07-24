# API Methods

[← Fiscal Receipts](receipts.md) · [Back to README](../README.md) · [Webhooks →](webhooks.md)

All methods are called through `api('<method>', [...])`. `secretKey` is added
automatically from the constructor, so pass only the business params below. Full
parameters and response formats are in the
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

## Account-level methods

For the account-level methods (`getCommissions`, `getCurrencyCourses`, `getPartner`,
`offsetAdvance` and all payout methods) the `secretKey` is the **account** key (profile),
not the project key, and `login` is the account email. Pass the account key explicitly in
the call — it overrides the constructor (project) key:

```php
$response = $unitpay->api('getPartner', [
    'login'     => 'partner@example.com',
    'secretKey' => $accountKey, // overrides the project key from the constructor
]);
```

For SBP payouts pass `memberId` obtained from `getSbpBankList`.

## Example — refund a payment

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

## See Also

* [Getting Started](getting-started.md) — the `initPayment` flow in full
* [Webhooks](webhooks.md) — handle the callbacks a payment triggers
