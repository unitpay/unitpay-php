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

The dictionaries live in `Unitpay\Model\Enum` as const-classes: `Nds`, `PaymentObject`,
`PaymentMethod`, `Measure` (and `PaymentType` for payment-method codes). They are plain
classes with `public const`, not native enums, because the SDK supports PHP 7.4.

> Since 2026 the backend fiscalizes `Nds::VAT20` (`vat20`) as VAT **22%** — there is no
> separate path for "real" 20%. Pick the rate that matches the actual receipt (see
> [CHANGELOG.md](../CHANGELOG.md)).

Some payment-object values are kept only for backward compatibility and are rejected by
the public API: `EXCISE`, `GAMBLING_BET`, `GAMBLING_PRIZE`, `LOTTERY_PRIZE`, `COMPOSITE`.
Do not use them in new code; they are slated for removal in 4.0.

## See Also

* [Getting Started](getting-started.md) — create a payment with `form()` or the API
* [API Methods](api-methods.md) — `offsetAdvance` and other receipt-related methods
