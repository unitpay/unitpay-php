<?php

namespace Unitpay\Model\Enum;

/**
 * Unit-of-measure codes (единица измерения) for a 54-FZ receipt line item.
 * Const-class rather than a native enum to keep PHP 7.4 support.
 */
final class Measure
{
    /** Piece, unit */
    public const ITEM = 0;
    /** Gram */
    public const G = 10;
    /** Kilogram */
    public const KG = 11;
    /** Tonne */
    public const T = 12;
    /** Centimeter */
    public const CM = 20;
    /** Decimeter */
    public const DM = 21;
    /** Meter */
    public const M = 22;
    /** Square centimeter */
    public const CM2 = 30;
    /** Square decimeter */
    public const DM2 = 31;
    /** Square meter */
    public const M2 = 32;
    /** Milliliter */
    public const ML = 40;
    /** Liter */
    public const L = 41;
    /** Cubic meter */
    public const M3 = 42;
    /** Kilowatt-hour */
    public const KWH = 50;
    /** Gigacalorie */
    public const GC = 51;
    /** Day (24 hours) */
    public const D = 70;
    /** Hour */
    public const H = 71;
    /** Minute */
    public const MIN = 72;
    /** Second */
    public const S = 73;
    /** Kilobyte */
    public const KB = 80;
    /** Megabyte */
    public const MB = 81;
    /** Gigabyte */
    public const GB = 82;
    /** Terabyte */
    public const TB = 83;
    /** Other unit of measure */
    public const OTHER = 255;
}
