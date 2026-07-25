<?php

namespace Unitpay\Model\Enum;

/**
 * VAT rate codes (НДС) for a 54-FZ receipt line item. Const-class rather than a
 * native enum to keep PHP 7.4 support. Source of truth is the backend.
 */
final class Nds
{
    /** No VAT */
    public const NONE = 'none';
    /** VAT at 0% */
    public const VAT0 = 'vat0';
    /** VAT at 5% */
    public const VAT5 = 'vat5';
    /** VAT at 7% */
    public const VAT7 = 'vat7';
    /** VAT at 10% */
    public const VAT10 = 'vat10';
    /**
     * VAT at 20%.
     * Note: since 2026 the backend issues a receipt with 22% VAT for this value
     * (the base VAT rate was raised). There is no separate path for "real" 20%
     * on the backend — vat20 and vat22 map to a single fiscal code.
     */
    public const VAT20 = 'vat20';
    /** VAT at 22% */
    public const VAT22 = 'vat22';
    /** VAT at the calculated rate 5/105 */
    public const VAT105 = 'vat105';
    /** VAT at the calculated rate 7/107 */
    public const VAT107 = 'vat107';
    /** VAT at the calculated rate 10/110 */
    public const VAT110 = 'vat110';
    /** VAT at the calculated rate 20/120 */
    public const VAT120 = 'vat120';
    /** VAT at the calculated rate 22/122 */
    public const VAT122 = 'vat122';
}
