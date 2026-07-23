<?php

/**
 * Marker interface implemented by every exception in this SDK, so that calling
 * code can catch any Unitpay error with a single catch. Each concrete class
 * additionally extends the SPL exception that was historically thrown, so
 * existing
 * catch (InvalidArgumentException | UnexpectedValueException) blocks keep working.
 */
interface UnitpayExceptionInterface
{
}

/** The webhook signature did not match (possible forgery). */
class UnitpaySignatureException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** The webhook arrived from an IP address outside the allowlist. */
class UnitpayIpException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** api() could not obtain a usable response from Unitpay (network or response parsing). */
class UnitpayTransportException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** The method is not supported by api() or the handler. */
class UnitpayUnsupportedMethodException extends UnexpectedValueException implements UnitpayExceptionInterface
{
}

/** An invalid or missing argument was passed (wrong parameter, missing key, malformed data). */
class UnitpayValidationException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/**
 * Immutable value object for a single fiscal-receipt line item (54-FZ).
 */
final class CashItem
{
    /** No VAT */
    public const NDS_NONE = 'none';
    /** VAT at 0% */
    public const NDS_0 = 'vat0';
    /** VAT at 5% */
    public const NDS_5 = 'vat5';
    /** VAT at 7% */
    public const NDS_7 = 'vat7';
    /** VAT at 10% */
    public const NDS_10 = 'vat10';
    /**
     * VAT at 20%.
     * Note: since 2026 the backend issues a receipt with 22% VAT for this value
     * (the base VAT rate was raised). There is no separate path for "real" 20%
     * on the backend — vat20 and vat22 map to a single fiscal code.
     */
    public const NDS_20 = 'vat20';
    /** VAT at 22% */
    public const NDS_22 = 'vat22';
    /** VAT at the calculated rate 5/105 */
    public const NDS_105 = 'vat105';
    /** VAT at the calculated rate 7/107 */
    public const NDS_107 = 'vat107';
    /** VAT at the calculated rate 10/110 */
    public const NDS_110 = 'vat110';
    /** VAT at the calculated rate 20/120 */
    public const NDS_120 = 'vat120';
    /** VAT at the calculated rate 22/122 */
    public const NDS_122 = 'vat122';

    /** Commodity */
    public const PAYMENT_OBJECT_COMMODITY = 'commodity';
    /** Job */
    public const PAYMENT_OBJECT_JOB = 'job';
    /** Service */
    public const PAYMENT_OBJECT_SERVICE = 'service';
    /** Lottery ticket */
    public const PAYMENT_OBJECT_LOTTERY = 'lottery';
    /** Intellectual activity results */
    public const PAYMENT_OBJECT_INTELLECTUAL_ACTIVITY = 'intellectual_activity';
    /** Payment (advance, deposit, prepayment, credit) */
    public const PAYMENT_OBJECT_PAYMENT = 'payment';
    /** Agent commission */
    public const PAYMENT_OBJECT_AGENT_COMMISSION = 'agent_commission';
    /** Contribution, penalty, fine, bonus and other similar payment object */
    public const PAYMENT_OBJECT_PAYMENT_2 = 'payment_2';
    /** Other payment object */
    public const PAYMENT_OBJECT_ANOTHER = 'another';
    /** Property right */
    public const PAYMENT_OBJECT_PROPERTY_RIGHT = 'property_right';
    /** Non-operating gain */
    public const PAYMENT_OBJECT_NON_OPERATING_GAIN = 'non-operating_gain';
    /** Insurance premiums */
    public const PAYMENT_OBJECT_INSURANCE_PREMIUM = 'insurance_premium';
    /** Sales tax */
    public const PAYMENT_OBJECT_SALES_TAX = 'sales_tax';
    /** Resort fee */
    public const PAYMENT_OBJECT_RESORT_FEE = 'resort_fee';
    /** Deposit */
    public const PAYMENT_OBJECT_DEPOSIT = 'deposit';
    /** Expense */
    public const PAYMENT_OBJECT_EXPENSE = 'expense';
    /** Mandatory pension insurance contributions for a sole proprietor */
    public const PAYMENT_OBJECT_PENSION_INSURANCE_IP = 'pension_insurance_ip';
    /** Mandatory pension insurance contributions */
    public const PAYMENT_OBJECT_PENSION_INSURANCE = 'pension_insurance';
    /** Mandatory medical insurance contributions for a sole proprietor */
    public const PAYMENT_OBJECT_MEDICAL_INSURANCE_IP = 'medical_insurance_ip';
    /** Mandatory medical insurance contributions */
    public const PAYMENT_OBJECT_MEDICAL_INSURANCE = 'medical_insurance';
    /** Mandatory social insurance contributions */
    public const PAYMENT_OBJECT_SOCIAL_INSURANCE = 'social_insurance';
    /** Casino payment */
    public const PAYMENT_OBJECT_CASINO_PAYMENT = 'casino_payment';
    /** Cash disbursement */
    public const PAYMENT_OBJECT_ISSUANCE_BANK = 'issuance_bank';
    /** Commodity subject to marking, without a mark code */
    public const PAYMENT_OBJECT_COMMODITY_WITHOUT_MARK = 'commodity_without_mark';
    /** Commodity subject to marking, with a mark code */
    public const PAYMENT_OBJECT_COMMODITY_MARK = 'commodity_mark';

    /** @deprecated Rejected by the public API; will be removed in 3.0. */
    public const PAYMENT_OBJECT_EXCISE = 'excise';
    /** @deprecated Rejected by the public API; will be removed in 3.0. */
    public const PAYMENT_OBJECT_GAMBLING_BET = 'gambling_bet';
    /** @deprecated Rejected by the public API; will be removed in 3.0. */
    public const PAYMENT_OBJECT_GAMBLING_PRIZE = 'gambling_prize';
    /** @deprecated Rejected by the public API; will be removed in 3.0. */
    public const PAYMENT_OBJECT_LOTTERY_PRIZE = 'lottery_prize';
    /** @deprecated Rejected by the public API; will be removed in 3.0. */
    public const PAYMENT_OBJECT_COMPOSITE = 'composite';

    /** 100% prepayment */
    public const PAYMENT_METHOD_PREPAYMENT_FULL = 'full_prepayment';
    /** Partial prepayment */
    public const PAYMENT_METHOD_PREPAYMENT = 'prepayment';
    /** Advance */
    public const PAYMENT_METHOD_ADVANCE = 'advance';
    /** Full payment */
    public const PAYMENT_METHOD_PAYMENT_FULL = 'full_payment';

    /** Piece, unit */
    public const MEASURE_ITEM = 0;
    /** Gram */
    public const MEASURE_G = 10;
    /** Kilogram */
    public const MEASURE_KG = 11;
    /** Tonne */
    public const MEASURE_T = 12;
    /** Centimeter */
    public const MEASURE_CM = 20;
    /** Decimeter */
    public const MEASURE_DM = 21;
    /** Meter */
    public const MEASURE_M = 22;
    /** Square centimeter */
    public const MEASURE_CM2 = 30;
    /** Square decimeter */
    public const MEASURE_DM2 = 31;
    /** Square meter */
    public const MEASURE_M2 = 32;
    /** Milliliter */
    public const MEASURE_ML = 40;
    /** Liter */
    public const MEASURE_L = 41;
    /** Cubic meter */
    public const MEASURE_M3 = 42;
    /** Kilowatt-hour */
    public const MEASURE_KWH = 50;
    /** Gigacalorie */
    public const MEASURE_GC = 51;
    /** Day (24 hours) */
    public const MEASURE_D = 70;
    /** Hour */
    public const MEASURE_H = 71;
    /** Minute */
    public const MEASURE_MIN = 72;
    /** Second */
    public const MEASURE_S = 73;
    /** Kilobyte */
    public const MEASURE_KB = 80;
    /** Megabyte */
    public const MEASURE_MB = 81;
    /** Gigabyte */
    public const MEASURE_GB = 82;
    /** Terabyte */
    public const MEASURE_TB = 83;
    /** Other unit of measure */
    public const MEASURE_OTHER = 255;

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
     * weight/volume goods (MEASURE_KG/G/L, ...), and the backend rounds the quantity to
     * 3 decimals, so casting to int would silently corrupt the receipt.
     *
     * @param int|float|string $count positive quantity (fractional allowed for weight/volume)
     * @param float|int|string $price non-negative price per unit
     */
    public function __construct(
        string $name,
        $count,
        $price,
        string $nds = self::NDS_NONE,
        string $type = self::PAYMENT_OBJECT_COMMODITY,
        string $paymentMethod = self::PAYMENT_METHOD_PREPAYMENT_FULL
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
     * Unit of measure, one of the MEASURE_* constants.
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
     * Allowed only when measure = MEASURE_ITEM and count = 1.
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

/**
 * Checks whether an IP is in the allowlist: exact addresses and CIDR subnets
 * (IPv4 and IPv6). Extracted from UnitPay into a separate class so the
 * range-matching logic stays cohesive and testable.
 */
final class UnitpayIpAllowlist
{
    /** @var string[] */
    private array $entries;

    /**
     * @param string[] $entries exact IPs and/or CIDR ranges (e.g. "77.75.153.0/25")
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public function contains(string $ip): bool
    {
        $ipBin = $this->toBinary($ip);
        foreach ($this->entries as $entry) {
            if (strpos($entry, '/') === false) {
                if ($entry === $ip) {
                    return true;
                }
                // Normalized comparison: the same address written differently
                // (case/IPv6 compression) yields the same packed in_addr.
                if ($ipBin !== null) {
                    $entryBin = $this->toBinary($entry);
                    if ($entryBin !== null && $entryBin === $ipBin) {
                        return true;
                    }
                }
                continue;
            }
            if ($ipBin !== null && $this->cidrContains($entry, $ipBin)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $ipBin packed in_addr of the client IP (from toBinary())
     */
    private function cidrContains(string $cidr, string $ipBin): bool
    {
        list($subnet, $bits) = explode('/', $cidr, 2);
        if (!ctype_digit($bits)) {
            return false;
        }
        $subnetBin = $this->toBinary($subnet);
        if ($subnetBin === null || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        return $this->prefixMatches($ipBin, $subnetBin, (int) $bits);
    }

    /**
     * Whether $entry is a valid allowlist entry: an exact IPv4/IPv6 address or a
     * CIDR range of the form "address/bits". Used to validate a fetched IP list
     * before it replaces the built-in one, so malformed JSON cannot empty the
     * allowlist.
     */
    public static function isValidEntry(string $entry): bool
    {
        if (strpos($entry, '/') === false) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        list($subnet, $bits) = explode('/', $entry, 2);
        if (!ctype_digit($bits) || filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // The prefix length cannot exceed the address width (IPv4 = 32, IPv6 = 128),
        // otherwise the entry looks valid but matches nothing (prefixMatches returns false).
        $maxBits = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
        return (int) $bits <= $maxBits;
    }

    /**
     * Parses the body of the published webhook IP feed ({"webhooks":[...]}) into a
     * validated, de-duplicated list of entries. Returns null on empty input,
     * malformed JSON, a missing or non-array "webhooks" key, or when no entry is a
     * valid IP/CIDR — so a bad feed cannot empty the allowlist.
     * @return string[]|null
     */
    public static function parseWebhooksFeed(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['webhooks']) || !is_array($data['webhooks'])) {
            return null;
        }
        $valid = [];
        foreach ($data['webhooks'] as $entry) {
            if (is_string($entry) && self::isValidEntry($entry)) {
                $valid[] = $entry;
            }
        }
        return $valid === [] ? null : array_values(array_unique($valid));
    }

    /**
     * @return string|null packed in_addr, or null if $ip is not a valid address
     */
    private function toBinary(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $binary = inet_pton($ip);
        return $binary === false ? null : $binary;
    }

    private function prefixMatches(string $ipBin, string $subnetBin, int $bits): bool
    {
        if ($bits > strlen($ipBin) * 8) {
            return false;
        }
        $whole = intdiv($bits, 8);
        if ($whole > 0 && strncmp($ipBin, $subnetBin, $whole) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $rest)) & 0xff);
        return ($ipBin[$whole] & $mask) === ($subnetBin[$whole] & $mask);
    }
}

/**
 * Client for the Unitpay payment REST API: signing and form/URL building,
 * server-to-server API calls, and inbound webhook verification.
 */
class UnitPay
{
    /** SDK version; sent in the telemetry fingerprint. Keep in sync with the release git tag. */
    public const VERSION = '2.1.0';

    /**
     * Payment method codes for the `paymentType` param in api('initPayment', ...)
     * and payouts api('massPayment', ...). The source of truth is the backend; code list:
     * https://help.unitpay.ru/book-of-reference/payment-system-codes
     * paymentType is NOT validated against these values (like the CashItem dictionaries), so
     * a new payment code does not require an SDK release — the constants only guard against
     * typos and provide autocompletion.
     */
    /** Bank cards (worldwide card acceptance) */
    public const PAYMENT_TYPE_CARD = 'card';
    /** Foreign cards via the acquiring bank's form */
    public const PAYMENT_TYPE_CARD_INVOICE = 'cardInvoice';
    /** Faster Payments System (SBP) */
    public const PAYMENT_TYPE_SBP = 'sbp';
    /** SberPay */
    public const PAYMENT_TYPE_SBERPAY = 'sberpay';
    /** Tinkoff Pay */
    public const PAYMENT_TYPE_TINKOFFPAY = 'tinkoffpay';
    /** PayPal */
    public const PAYMENT_TYPE_PAYPAL = 'paypal';
    /** WebMoney (WMZ wallets) */
    public const PAYMENT_TYPE_WEBMONEY = 'webmoney';

    /**
     * Pre-flight error codes for optional telemetry (Layer B). Stable, non-PII;
     * additive-only — do not rename or remove, so the backend series stay comparable.
     * ERR_API_UNREACHABLE is defined but NOT sent: the beacon would go to the same
     * unreachable $domain (see reportTelemetry / checkHandlerRequest wire-in).
     */
    public const ERR_METHOD_NOT_SUPPORTED   = 'ERR_METHOD_NOT_SUPPORTED';
    public const ERR_MISSING_REQUIRED_PARAM = 'ERR_MISSING_REQUIRED_PARAM';
    public const ERR_MISSING_SECRET_KEY     = 'ERR_MISSING_SECRET_KEY';
    public const ERR_API_UNREACHABLE        = 'ERR_API_UNREACHABLE';
    public const ERR_MISSING_METHOD         = 'ERR_MISSING_METHOD';
    public const ERR_MISSING_PARAMS         = 'ERR_MISSING_PARAMS';
    public const ERR_WRONG_SIGNATURE        = 'ERR_WRONG_SIGNATURE';
    public const ERR_IP_NOT_ALLOWED         = 'ERR_IP_NOT_ALLOWED';

    /**
     * Supported api() methods and their required parameters. secretKey is
     * injected and validated in api(), so it is not listed here.
     * @var array<string, string[]>
     */
    private array $requiredUnitpayMethodsParams = [
        'initPayment'         => ['account', 'sum', 'projectId', 'paymentType'],
        'getPayment'          => ['paymentId'],
        'refundPayment'       => ['paymentId'],
        'confirmPayment'      => ['paymentId'],
        'cancelPayment'       => ['paymentId'],
        'listSubscriptions'   => ['projectId'],
        'getSubscription'     => ['subscriptionId'],
        'closeSubscription'   => ['subscriptionId'],
        'getMethodsAvailable' => ['projectId'],
        'getCommissions'      => ['projectId', 'login'],
        'getCurrencyCourses'  => ['login'],
        'getPartner'          => ['login'],
        'offsetAdvance'       => ['login', 'paymentId'],
        'massPayment'                => ['login', 'transactionId', 'sum', 'purse', 'paymentType'],
        'massPaymentStatus'          => ['login', 'transactionId'],
        'massPaymentAvailableAmount' => ['login', 'sum', 'purse', 'paymentType'],
        'massPaymentCommissions'     => ['login'],
        'getSbpBankList'             => ['login'],
        'getBinInfo'                 => ['login', 'bin'],
    ];
    /**
     * Webhook methods that Unitpay sends to the handler. 'preauth' is a notification of
     * a two-stage hold on funds (money is blocked but not yet captured): it must pass
     * verification like the others rather than be rejected as unsupported.
     * @var string[]
     */
    private array $supportedPartnerMethods = ['check', 'pay', 'preauth', 'error'];
    /**
     * Published outbound Unitpay IPs. 127.0.0.1 is deliberately NOT here: behind a
     * reverse proxy on the same host REMOTE_ADDR equals 127.0.0.1, which would turn the
     * IP check into a sham. Add it explicitly via setAllowedIps() for local debugging only.
     * @var string[]
     */
    private array $supportedUnitpayIp = [
        '31.186.100.49',
        '51.250.20.9',
    ];

    private ?string $secretKey;
    /** @var array<string, mixed> */
    private array $params = [];
    private string $apiUrl;
    private string $formUrl;
    /** @var callable|null */
    private $transport;
    /** @var array<string, mixed>|null */
    private ?array $request;
    private ?string $clientIp;
    private ?string $handlerMethod = null;
    /** @var array<string, mixed>|null */
    private ?array $handlerParams = null;
    private ?UnitpayIpAllowlist $ipAllowlist = null;
    /**
     * The merchant's own IPs, added via addAllowedIps(); always applied on top of
     * the Unitpay list and preserved across refreshAllowedIps()/setAllowedIps().
     * @var string[]
     */
    private array $customIps = [];
    private string $ipsUrl;
    private string $telemetryUrl;
    private bool $telemetryEnabled = false;

    /**
     * @param string $domain host only, e.g. "unitpay.ru" — without scheme or path (becomes "https://$domain/api").
     * @param callable|null $transport outbound HTTP transport for api(): fn(string $url): string|false.
     *                                 Defaults to file_get_contents(). Override to test api() without the network.
     * @param array<string, mixed>|null $request inbound webhook array read by checkHandlerRequest().
     *                                 Defaults to $_GET. Override to test the handler without superglobals.
     * @param string|null $clientIp    sender IP used by getIp(). Defaults to $_SERVER['REMOTE_ADDR'].
     *                                 Override to test the IP allowlist without superglobals.
     */
    public function __construct(string $domain, ?string $secretKey = null, ?callable $transport = null, ?array $request = null, ?string $clientIp = null)
    {
        $this->secretKey = $secretKey;
        $this->apiUrl = "https://$domain/api";
        $this->formUrl = "https://$domain/pay/";
        $this->ipsUrl = "https://$domain/ips/ips_webhooks.json";
        $this->telemetryUrl = "https://$domain/sdk/telemetry";
        $this->transport = $transport;
        $this->request = $request;
        $this->clientIp = $clientIp;
    }

    /**
     * Overrides the list of Unitpay IPs allowed to call the handler.
     * Fully replaces the built-in default (or previously fetched) list, but does
     * NOT touch the merchant IPs added via addAllowedIps() — they remain on top.
     * Use it to keep the SDK current when Unitpay's infrastructure changes without
     * waiting for a release, or to restore a list you fetched and cached yourself.
     * @link https://help.unitpay.ru/book-of-reference/ip-addresses
     * @param string[] $ips
     */
    public function setAllowedIps(array $ips): self
    {
        $this->supportedUnitpayIp = $ips;
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Adds the merchant's own IP/CIDR ranges (e.g. your proxy/relay) on top of the
     * Unitpay list. Unlike setAllowedIps(), which replaces the Unitpay list, these
     * are preserved across refreshAllowedIps()/setAllowedIps() calls. Duplicates
     * are removed.
     * @param string[] $ips exact IPs and/or CIDR ranges
     */
    public function addAllowedIps(array $ips): self
    {
        $this->customIps = array_values(array_unique(array_merge($this->customIps, $ips)));
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Fetches Unitpay's current published webhook IPs from
     * https://<domain>/ips/ips_webhooks.json and makes them the allowlist.
     *
     * Best-effort and fail-safe: on any transport/parse/validation error the
     * previously configured Unitpay list (built-in default or the last
     * setAllowedIps()) is left unchanged — the method never empties the list and
     * never throws, so it is safe to call in a chain before checkHandlerRequest().
     * A successful fetch REPLACES the Unitpay list (so a decommissioned IP drops
     * out); merchant IPs added via addAllowedIps() are preserved and always applied
     * on top.
     *
     * TLS verification matters here (httpGet keeps CURLOPT_SSL_VERIFYPEER / verify_peer
     * enabled): an unverified or spoofed list would defeat the IP check.
     *
     * The method makes a blocking network request — call it periodically (e.g. from a
     * daily cron) and cache getAllowedIps() yourself; do NOT call it on every webhook.
     */
    public function refreshAllowedIps(): self
    {
        $ips = $this->fetchUnitpayIps();
        if ($ips !== null) {
            $this->supportedUnitpayIp = $ips;
            $this->ipAllowlist = null;
        }
        return $this;
    }

    /**
     * The effective allowlist actually applied by the handler: the Unitpay list plus
     * the merchant additions, de-duplicated. Cache it after refreshAllowedIps() and
     * feed it back via setAllowedIps() when handling webhooks, to avoid a network
     * request on every call.
     * @return string[]
     */
    public function getAllowedIps(): array
    {
        return array_values(array_unique(array_merge($this->supportedUnitpayIp, $this->customIps)));
    }

    /**
     * Fetches and validates the published webhook IP feed.
     * @return string[]|null validated non-empty list, or null on any error
     */
    private function fetchUnitpayIps(): ?array
    {
        $body = $this->httpGet($this->ipsUrl);
        return is_string($body) ? UnitpayIpAllowlist::parseWebhooksFeed($body) : null;
    }

    /**
     * Builds the SHA-256 signature: parameter values sorted with ksort and joined
     * by the literal "{up}" delimiter, with $method prepended and secretKey
     * appended.
     *
     * Security: unset() strips the caller-supplied signature keys AND the PHP_INT_MAX
     * index — a forged params[PHP_INT_MAX] would turn the secretKey append into a
     * no-op, dropping the secret from the hash and making signatures forgeable (bypass
     * on PHP <8, fatal Error/DoS on PHP >=8). Do NOT remove this unset — the guard was
     * once lost in 7835fb4 and restored. A forged webhook may also inject an array
     * value (e.g. params[x][]=1), so non-scalars are coerced to '' — implode() emits no
     * warning and verification still fails, because the secret is appended regardless.
     *
     * @param array<array-key, mixed> $params
     */
    public function getSignature(array $params, ?string $method = null): string
    {
        unset($params['sign'], $params['signature'], $params[PHP_INT_MAX]);
        ksort($params);
        $params[] = $this->secretKey;

        if ($method !== null) {
            array_unshift($params, $method);
        }

        $params = array_map(static function ($value) {
            if (is_float($value)) {
                return self::floatToString($value);
            }
            return is_scalar($value) ? $value : '';
        }, $params);

        return hash('sha256', implode('{up}', $params));
    }

    /**
     * Sender IP of the inbound request (the overridden clientIp or $_SERVER['REMOTE_ADDR']).
     */
    protected function getIp(): string
    {
        return $this->clientIp !== null ? $this->clientIp : ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Whether $ip is allowed to call the handler. Matches exact addresses and CIDR
     * subnets (IPv4/IPv6) via UnitpayIpAllowlist, so setAllowedIps(['77.75.153.0/25'])
     * works. Override for proxy-aware logic.
     */
    protected function isAllowedIp(string $ip): bool
    {
        if ($this->ipAllowlist === null) {
            $this->ipAllowlist = new UnitpayIpAllowlist(
                array_merge($this->supportedUnitpayIp, $this->customIps)
            );
        }
        return $this->ipAllowlist->contains($ip);
    }

    /**
     * Performs the outbound HTTP GET used by api().
     * Selection order: overridden $transport -> cURL (if ext-curl is present) -> file_get_contents.
     * cURL adds connect/read timeouts and does not require allow_url_fopen; both
     * fallbacks have a timeout too. Returns the response body, or false on a
     * transport error (which api() turns into "Temporary server error").
     *
     * Security: TLS verification stays enabled (cURL keeps CURLOPT_SSL_VERIFYPEER at
     * its default true). The file_get_contents fallback suppresses its transport
     * warning via set_error_handler, not the '@' operator (which QA rules forbid) —
     * otherwise that warning would log the URL together with the secret.
     * @param string[] $headers HTTP headers of the form "Name: value" (Layer A fingerprint / beacon).
     * @param int|null $timeoutMs hard timeout in ms (Layer B beacon); null uses api()'s normal timeouts.
     * @return string|false
     */
    protected function httpGet(string $url, array $headers = [], ?int $timeoutMs = null)
    {
        if ($this->transport !== null) {
            return call_user_func($this->transport, $url, $headers, $timeoutMs);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
            ];
            if ($timeoutMs !== null) {
                // Millisecond timeouts + NOSIGNAL for the best-effort Layer B beacon (replacing the second-based ones).
                unset($opts[CURLOPT_CONNECTTIMEOUT], $opts[CURLOPT_TIMEOUT]);
                $opts[CURLOPT_NOSIGNAL] = true;
                $opts[CURLOPT_CONNECTTIMEOUT_MS] = $timeoutMs;
                $opts[CURLOPT_TIMEOUT_MS] = $timeoutMs;
            }
            if ($headers !== []) {
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            if (\PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            return $body;
        }

        $http = ['timeout' => $timeoutMs !== null ? $timeoutMs / 1000 : 10];
        if ($headers !== []) {
            $http['header'] = implode("\r\n", $headers);
        }
        $context = stream_context_create(['http' => $http]);
        set_error_handler(static function () {
            return true;
        });
        try {
            return file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Builds the redirect URL to Unitpay's hosted payment form. Parameters set via
     * the fluent setters (setCashItems/setCustomerEmail/setBackUrl/...) are merged in
     * and then cleared, so a reused instance does not carry this call's parameters
     * into the next form()/api().
     * @param string|float|int $sum
     */
    public function form(string $publicKey, $sum, string $account, string $desc, string $currency = 'RUB', string $locale = 'ru'): string
    {
        if (empty($this->secretKey)) {
            throw new UnitpayValidationException('SecretKey is null');
        }
        $vitalParams = self::stringifyFloats([
            'account'  => $account,
            'currency' => $currency,
            'desc'     => $desc,
            'sum'      => $sum,
        ]);
        $params = array_merge($this->params, $vitalParams);
        $params['signature'] = $this->getSignature($vitalParams);
        $params['locale'] = $locale;
        $params['sdk'] = $this->getSdkToken(); // outside the signature — does not affect it (Layer A)
        $this->params = [];
        return $this->formUrl . $publicKey . '?' . http_build_query($params);
    }

    /**
     * Sets the customer's email.
     */
    public function setCustomerEmail(string $email): self
    {
        $this->params['customerEmail'] = $email;
        return $this;
    }

    /**
     * Sets the customer's phone.
     */
    public function setCustomerPhone(string $phone): self
    {
        $this->params['customerPhone'] = $phone;
        return $this;
    }

    /**
     * Attaches a fiscal receipt (54-FZ line items) to the next form()/api() call.
     * Optional CashItem fields are serialized only when set. Throws instead of
     * sending an empty receipt if json_encode fails (e.g. a name is not UTF-8 /
     * is Windows-1251).
     * @param CashItem[] $items
     */
    public function setCashItems(array $items): self
    {
        $cashItems = array_map(static function ($item) {
            /** @var CashItem $item */
            $cashItem = [
                'name'          => $item->getName(),
                'count'         => $item->getCount(),
                'price'         => $item->getPrice(),
                'nds'           => $item->getNds(),
                'type'          => $item->getType(),
                'paymentMethod' => $item->getPaymentMethod(),
            ];

            $optional = [
                'sum'              => $item->getSum(),
                'currency'         => $item->getCurrency(),
                'measure'          => $item->getMeasure(),
                'nomenclatureCode' => $item->getNomenclatureCode(),
                'markCode'         => $item->getMarkCode(),
                'markQuantity'     => $item->getMarkQuantity(),
                'pre_text'         => $item->getPreText(),
                'post_text'        => $item->getPostText(),
            ];
            foreach ($optional as $key => $value) {
                if ($value !== null) {
                    $cashItem[$key] = $value;
                }
            }

            return $cashItem;
        }, $items);

        $json = json_encode($cashItems);
        if ($json === false) {
            throw new UnitpayValidationException('Failed to encode cashItems: ' . json_last_error_msg());
        }
        $this->params['cashItems'] = base64_encode($json);

        return $this;
    }

    /**
     * Sets the URL Unitpay will return the payer to after payment.
     */
    public function setBackUrl(string $backUrl): self
    {
        $this->params['backUrl'] = $backUrl;
        return $this;
    }

    /**
     * Performs a server-to-server call to the Unitpay REST API. Fluent-setter params
     * are merged in (so setCashItems()->api('initPayment', ...) sends the receipt) and
     * cleared only by a SUCCESSFUL call. A transport failure keeps them, so a retry
     * goes out with the same receipt — hence the state is clean only after success:
     * an unrelated call right AFTER a failure inherits the accumulated params (reset
     * them explicitly or use a new instance if that is undesirable). Explicit $params
     * take precedence. An explicit non-empty secretKey in $params overrides the
     * instance key, so account-level methods (getPartner, getCommissions, payouts, ...)
     * can use the account key.
     * @param array<string, mixed> $params
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function api(string $method, array $params = []): object
    {
        if (!isset($this->requiredUnitpayMethodsParams[$method])) {
            $this->reportTelemetry(self::ERR_METHOD_NOT_SUPPORTED, $method);
            throw new UnitpayUnsupportedMethodException('Method is not supported');
        }

        $params = array_merge($this->params, $params);

        foreach ($this->requiredUnitpayMethodsParams[$method] as $rParam) {
            if (!isset($params[$rParam])) {
                $this->reportTelemetry(self::ERR_MISSING_REQUIRED_PARAM, $method);
                throw new UnitpayValidationException('Param ' . $rParam . ' is null');
            }
        }

        if (empty($params['secretKey'])) {
            $params['secretKey'] = $this->secretKey;
        }
        if (empty($params['secretKey'])) {
            $this->reportTelemetry(self::ERR_MISSING_SECRET_KEY, $method);
            throw new UnitpayValidationException('SecretKey is null');
        }

        $params = self::stringifyFloats($params);

        $requestUrl = $this->apiUrl . '?' . http_build_query(
            ['method' => $method] + $params,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $response = json_decode($this->httpGet($requestUrl, $this->fingerprintHeaders()));
        if (!is_object($response)) {
            throw new UnitpayTransportException('Temporary server error. Please try again later.');
        }

        $this->params = [];

        return $response;
    }

    /**
     * Verifies the inbound webhook: supported method, SHA-256 signature (constant-time)
     * and the sender IP allowlist. On success it sets the verified method and params,
     * available via getHandlerMethod()/getHandlerParams() (honoring the overridden
     * request, not $_GET).
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function checkHandlerRequest(): bool
    {
        $ip = $this->getIp();
        if (empty($this->secretKey)) {
            $this->reportTelemetry(self::ERR_MISSING_SECRET_KEY, 'unknown');
            throw new UnitpayValidationException('SecretKey is null');
        }

        $request = $this->request !== null ? $this->request : $_GET;

        if (!isset($request['method'])) {
            $this->reportTelemetry(self::ERR_MISSING_METHOD, 'unknown');
            throw new UnitpayValidationException('Method is null');
        }

        if (!isset($request['params'])) {
            $this->reportTelemetry(self::ERR_MISSING_PARAMS, 'unknown');
            throw new UnitpayValidationException('Params is null');
        }

        list($method, $params) = [$request['method'], $request['params']];

        if (!in_array($method, $this->supportedPartnerMethods, true)) {
            // method here is arbitrary sender input; do not echo it into telemetry.
            $this->reportTelemetry(self::ERR_METHOD_NOT_SUPPORTED, 'unknown');
            throw new UnitpayUnsupportedMethodException('Method is not supported');
        }

        if (!isset($params['signature']) || !is_string($params['signature'])
            || !hash_equals($this->getSignature($params, $method), $params['signature'])) {
            $this->reportTelemetry(self::ERR_WRONG_SIGNATURE, $method);
            throw new UnitpaySignatureException('Wrong signature');
        }

        if (!$this->isAllowedIp($ip)) {
            $this->reportTelemetry(self::ERR_IP_NOT_ALLOWED, $method);
            throw new UnitpayIpException('IP address Error');
        }

        $this->handlerMethod = $method;
        $this->handlerParams = $params;

        return true;
    }

    /**
     * The webhook method verified by the last successful checkHandlerRequest()
     * ('check' | 'pay' | 'preauth' | 'error'). Read it instead of $_GET so the
     * overridden request is honored. null until a successful verification.
     */
    public function getHandlerMethod(): ?string
    {
        return $this->handlerMethod;
    }

    /**
     * The webhook params verified by the last successful checkHandlerRequest().
     * null until a successful verification.
     * @return array<string, mixed>|null
     */
    public function getHandlerParams(): ?array
    {
        return $this->handlerParams;
    }

    /**
     * Machine-readable fingerprint token for the form() URL: <platform>_<SDK version>_<PHP major.minor>.
     * URL-safe characters only (http_build_query leaves them unencoded); major.minor so the exact
     * PHP patch is not exposed in the buyer-visible payment form URL.
     */
    private function getSdkToken(): string
    {
        return 'php_' . self::VERSION . '_' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    /**
     * SDK self-identification string for the User-Agent header (full PHP version —
     * the header is invisible to the buyer and useful for diagnostics).
     */
    private function getUserAgent(): string
    {
        return 'unitpay-php-sdk/' . self::VERSION . ' php/' . PHP_VERSION;
    }

    /**
     * JSON fingerprint for the X-Unitpay-Client header — a machine-readable version of
     * the UA, so the backend does not have to parse the User-Agent string with a regex.
     */
    private function getClientHeader(): string
    {
        return (string) json_encode([
            'platform'    => 'php',
            'sdk_version' => self::VERSION,
            'php_version' => PHP_VERSION,
        ]);
    }

    /**
     * Fingerprint headers for the header channels (api() and the Layer B beacon).
     * @return string[]
     */
    private function fingerprintHeaders(): array
    {
        return [
            'User-Agent: ' . $this->getUserAgent(),
            'X-Unitpay-Client: ' . $this->getClientHeader(),
        ];
    }

    /**
     * Enables optional pre-flight error telemetry (Layer B). Disabled by default.
     * The merchant only toggles the flag — the endpoint URL is derived from $domain and
     * need not be passed. Fully silenced by the UNITPAY_SDK_TELEMETRY_DISABLE environment
     * variable (1/true/yes) with no code change.
     */
    public function enableTelemetry(): self
    {
        $this->telemetryEnabled = true;
        return $this;
    }

    /**
     * Best-effort pre-flight error beacon. Never throws and never affects the payment
     * flow: a no-op when telemetry is disabled or the env kill switch is set; hard
     * 300 ms timeout; sends only non-PII fields (sdk, php, error, method).
     * @param string $code one of the ERR_* constants
     * @param string $method method name or 'unknown'
     */
    private function reportTelemetry(string $code, string $method): void
    {
        if (!$this->telemetryEnabled) {
            return;
        }
        $disable = getenv('UNITPAY_SDK_TELEMETRY_DISABLE');
        if ($disable !== false && in_array(strtolower(trim($disable)), ['1', 'true', 'yes'], true)) {
            return;
        }
        $query = http_build_query([
            'sdk'    => self::VERSION,
            'php'    => PHP_VERSION,
            'error'  => $code,
            'method' => $method,
        ]);
        try {
            $this->httpGet($this->telemetryUrl . '?' . $query, $this->fingerprintHeaders(), 300);
        } catch (\Throwable $e) {
            // swallow — telemetry must not affect the payment flow
        }
    }

    /**
     * Converts float params to locale-independent decimal strings so the signature and
     * request URL match on PHP <8.0 (where (string)$float honors LC_NUMERIC and would
     * yield "100,5" in comma locales). Non-float values pass through unchanged.
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function stringifyFloats(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_float($value)) {
                $params[$key] = self::floatToString($value);
            }
        }

        return $params;
    }

    /**
     * Converts a float to a locale-independent decimal string without trailing zeros.
     * (string) $float honors LC_NUMERIC on PHP <8.0 and would yield "100,5" in comma
     * locales, breaking the signature/URL match. Shared by getSignature() and
     * stringifyFloats() so the signature and the transmitted value look identical.
     */
    private static function floatToString(float $value): string
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
    }

    /**
     * Builds the JSON success response that Unitpay expects from the handler.
     */
    public function getSuccessHandlerResponse(string $message): string
    {
        return (string) json_encode(['result' => ['message' => $message]]);
    }

    /**
     * Builds the JSON error response that Unitpay expects from the handler.
     */
    public function getErrorHandlerResponse(string $message): string
    {
        return (string) json_encode(['error' => ['message' => $message]]);
    }
}
