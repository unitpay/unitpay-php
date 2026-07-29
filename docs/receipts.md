# Fiscal Receipts (54-FZ)

[← Getting Started](getting-started.md) · [Back to README](../README.md) · [API Methods →](api-methods.md)

Attach receipt line items with `CashItem` and `setCashItems()` (works with both `form()`
and `payments()->initPayment(...)`). The constructor takes the required fields; optional
fields are set via fluent setters and are serialized only when set:

```php
use Unitpay\Model\CashItem;
use Unitpay\Model\Enum\Measure;
use Unitpay\Model\Enum\Nds;
use Unitpay\Model\Enum\PaymentMethod;
use Unitpay\Model\Enum\PaymentObject;

$item = new CashItem(
    'Iphone 6 Skin Cover',      // name
    1,                          // count
    900,                        // price
    Nds::VAT20,                 // VAT rate
    PaymentObject::COMMODITY,   // payment object
    PaymentMethod::PAYMENT_FULL
);
$item->setMeasure(Measure::ITEM);

$unitpay->setCashItems([$item]);
```

The receipt waits on the instance until one of the three calls that accept it runs —
`form()`, `payments()->initPayment()` or `payments()->offsetAdvance()` — and is cleared
there. Other service calls ignore it, so a lookup in between is harmless:

```php
$unitpay->setCashItems([$item]);
$unitpay->payments()->getPayment($someOtherId);   // receipt untouched
$unitpay->payments()->initPayment(...);           // receipt arrives here
```

A consuming call clears the receipt even if the request fails, so a retry must call
`setCashItems()` again. See [Fluent parameters](api-methods.md#fluent-parameters).

The dictionaries live in `Unitpay\Model\Enum` as const-classes: `Nds`, `PaymentObject`,
`PaymentMethod`, `Measure` (and `PaymentType` for payment-method codes). They are plain
classes with `public const`, not native enums, because the SDK supports PHP 7.4.

> Since 2026 the backend fiscalizes `Nds::VAT20` (`vat20`) as VAT **22%** — there is no
> separate path for "real" 20%. Pick the rate that matches the actual receipt (see
> [CHANGELOG.md](../CHANGELOG.md)).

## See Also

* [Getting Started](getting-started.md) — create a payment with `form()` or the API
* [API Methods](api-methods.md) — `offsetAdvance` and other receipt-related methods
