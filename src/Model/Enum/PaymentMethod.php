<?php

namespace Unitpay\Model\Enum;

/**
 * Payment-method codes (признак способа расчёта) for a 54-FZ receipt line item.
 * Const-class rather than a native enum to keep PHP 7.4 support.
 */
final class PaymentMethod
{
    /** 100% prepayment */
    public const PREPAYMENT_FULL = 'full_prepayment';
    /** Partial prepayment */
    public const PREPAYMENT = 'prepayment';
    /** Advance */
    public const ADVANCE = 'advance';
    /** Full payment */
    public const PAYMENT_FULL = 'full_payment';
}
