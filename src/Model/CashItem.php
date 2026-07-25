<?php

namespace Unitpay\Model;

use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Model\Enum\Nds;
use Unitpay\Model\Enum\PaymentMethod;
use Unitpay\Model\Enum\PaymentObject;

/**
 * Immutable value object for a single fiscal-receipt line item (54-FZ).
 *
 * Dictionaries live in Unitpay\Model\Enum (Nds, PaymentObject, PaymentMethod,
 * Measure): pass those constants to the constructor and setMeasure().
 */
final class CashItem
{
    private string $name;
    /** @var int|float */
    private $count;
    private float $price;
    private string $nds;
    private string $type;
    private string $paymentMethod;
    private ?float $sum = null;
    private ?string $currency = null;
    private ?int $measure = null;
    private ?string $nomenclatureCode = null;
    private ?string $markCode = null;
    /** @var array{numerator: int, denominator: int}|null */
    private ?array $markQuantity = null;
    private ?string $preText = null;
    private ?string $postText = null;

    /**
     * $count and $price are checked with is_numeric() BEFORE the range check: on PHP 8
     * comparing a non-numeric string to a number ("abc" <= 0) is performed as a string
     * comparison and yields false, so an unchecked value would pass as valid.
     * $count is stored as-is (int or float): fractional quantities are allowed for
     * weight/volume goods (Measure::KG/G/L, ...), and the backend rounds the quantity to
     * 3 decimals, so casting to int would silently corrupt the receipt.
     *
     * @param int|float|string $count positive quantity (fractional allowed for weight/volume)
     * @param float|int|string $price non-negative price per unit
     */
    public function __construct(
        string $name,
        $count,
        $price,
        string $nds = Nds::NONE,
        string $type = PaymentObject::COMMODITY,
        string $paymentMethod = PaymentMethod::PREPAYMENT_FULL
    ) {
        if (!is_numeric($count) || $count <= 0) {
            throw new UnitpayValidationException('CashItem count must be a positive number');
        }
        if (!is_numeric($price) || $price < 0) {
            throw new UnitpayValidationException('CashItem price must be a non-negative number');
        }
        $this->name = $name;
        $this->count = $count + 0;
        $this->price = (float) $price;
        $this->nds = $nds;
        $this->type = $type;
        $this->paymentMethod = $paymentMethod;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int|float
     */
    public function getCount()
    {
        return $this->count;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getNds(): string
    {
        return $this->nds;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * Total sum of the line item. If not set, the backend computes it as price * count.
     * Cannot exceed round(price * count, 2).
     */
    public function setSum(float $sum): self
    {
        $this->sum = $sum;
        return $this;
    }

    public function getSum(): ?float
    {
        return $this->sum;
    }

    /**
     * Line-item currency (ISO 4217). Defaults to RUB on the backend.
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * Unit of measure, one of the Unitpay\Model\Enum\Measure constants.
     */
    public function setMeasure(int $measure): self
    {
        $this->measure = $measure;
        return $this;
    }

    public function getMeasure(): ?int
    {
        return $this->measure;
    }

    /**
     * Product nomenclature code (marking).
     */
    public function setNomenclatureCode(string $nomenclatureCode): self
    {
        $this->nomenclatureCode = $nomenclatureCode;
        return $this;
    }

    public function getNomenclatureCode(): ?string
    {
        return $this->nomenclatureCode;
    }

    /**
     * Product mark code.
     */
    public function setMarkCode(string $markCode): self
    {
        $this->markCode = $markCode;
        return $this;
    }

    public function getMarkCode(): ?string
    {
        return $this->markCode;
    }

    /**
     * Fractional quantity of a marked product.
     * Allowed only when measure = Measure::ITEM and count = 1.
     */
    public function setMarkQuantity(int $numerator, int $denominator): self
    {
        if ((int) $numerator <= 0) {
            throw new UnitpayValidationException('CashItem markQuantity numerator must be a positive integer');
        }
        if ((int) $denominator <= 0) {
            throw new UnitpayValidationException('CashItem markQuantity denominator must be a positive integer');
        }
        $this->markQuantity = [
            'numerator'   => (int) $numerator,
            'denominator' => (int) $denominator,
        ];
        return $this;
    }

    /**
     * @return array{numerator: int, denominator: int}|null
     */
    public function getMarkQuantity(): ?array
    {
        return $this->markQuantity;
    }

    /**
     * Text shown before the line item on the receipt.
     */
    public function setPreText(string $preText): self
    {
        $this->preText = $preText;
        return $this;
    }

    public function getPreText(): ?string
    {
        return $this->preText;
    }

    /**
     * Text shown after the line item on the receipt.
     */
    public function setPostText(string $postText): self
    {
        $this->postText = $postText;
        return $this;
    }

    public function getPostText(): ?string
    {
        return $this->postText;
    }
}
