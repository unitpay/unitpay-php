# Fiscal Receipts (54-FZ)

[← Getting Started](getting-started.md) · [Back to README](../README.md) · [API Methods →](api-methods.md)

Attach receipt line items with `CashItem` and `setCashItems()` (works with both `form()`
and `api('initPayment', ...)`). The constructor takes the required fields; optional fields
are set via fluent setters and are serialized only when set:

```php
$item = new CashItem(
    'Iphone 6 Skin Cover',              // name
    1,                                  // count
    900,                                // price
    CashItem::NDS_20,                   // VAT rate
    CashItem::PAYMENT_OBJECT_COMMODITY, // payment object
    CashItem::PAYMENT_METHOD_PAYMENT_FULL
);
$item->setMeasure(CashItem::MEASURE_ITEM);

$unitpay->setCashItems([$item]);
```

VAT rates (`NDS_*`), payment objects (`PAYMENT_OBJECT_*`), payment methods
(`PAYMENT_METHOD_*`) and units of measure (`MEASURE_*`) are exposed as constants on
`CashItem`.

> Since 2026 the backend fiscalizes `NDS_20` (`vat20`) as VAT **22%** — there is no
> separate path for "real" 20%. Pick the rate that matches the actual receipt (see
> [CHANGELOG.md](../CHANGELOG.md)).

## See Also

* [Getting Started](getting-started.md) — create a payment with `form()` or `api()`
* [API Methods](api-methods.md) — `offsetAdvance` and other receipt-related methods
