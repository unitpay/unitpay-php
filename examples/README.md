# Examples

Ready-made Unitpay integration scenarios. The examples read `$_GET`/`$_SERVER` and call
`header()`, so they must be served over HTTP, not run from the CLI:

```sh
php -S localhost:8000 -t examples
# then open e.g. http://localhost:8000/paymentInfo.php
```

## Configuration

Shared data lives in two include files (not runnable on their own):

- [config.php](config.php) — connection and keys: `domain`, `projectId`, `publicId`,
  `secretKey`, plus `login`/`accountSecretKey` for account-level methods.
- [order.php](order.php) — order data: `orderId`, `orderSum`, `orderDesc`,
  `orderCurrency`. Included by payment examples in addition to `config.php`.

Secrets are not stored in code — they are read from the environment (with default placeholders):

```sh
export UNITPAY_SECRET_KEY=...            # project key
export UNITPAY_LOGIN=...                 # account login (account-level methods)
export UNITPAY_ACCOUNT_SECRET_KEY=...    # account key
```

## Scenarios

| File | Scenario |
| --- | --- |
| [paymentForm.php](paymentForm.php) | Unitpay-hosted payment form: `form()` builds the URL to the payment page; fluent setters (`setBackUrl`/`setCustomerEmail`/`setCustomerPhone`). |
| [initPaymentApi.php](initPaymentApi.php) | Server-to-server `initPayment`: handling the `redirect` / `invoice` / `response` reply. |
| [receipt.php](receipt.php) | 54-FZ fiscal receipt: line items via `CashItem` + `setCashItems()`. |
| [webhook.php](webhook.php) | Webhook handler: signature and IP verification, `check`/`pay`/`preauth`/`error` responses. |
| [paymentInfo.php](paymentInfo.php) | Payment info (`getPayment`). |
| [refund.php](refund.php) | Payment refund, full or partial (`refundPayment`). |
| [twoStagePayment.php](twoStagePayment.php) | Two-stage payment: `confirmPayment` (capture) / `cancelPayment` (release). |
| [subscriptions.php](subscriptions.php) | Subscriptions: list, info, close. |
| [payout.php](payout.php) | Payouts (mass-payment) via SBP + status. |
| [accountInfo.php](accountInfo.php) | Reference calls (read-only): balance, commissions, currency rates, BIN, payment methods. |
| [offsetAdvance.php](offsetAdvance.php) | Advance-offset receipt (`offsetAdvance`) — creates a fiscal receipt for a prepayment. |

## Webhook handler locally

By default `127.0.0.1` is not trusted. For local debugging of webhook retries from the same
host only, enable it with an explicit flag (and **never** in production):

```sh
UNITPAY_DEBUG_LOCAL=1 php -S localhost:8000 -t examples
```
