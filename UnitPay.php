<?php

/**
 * Marker interface implemented by every exception this SDK throws, so callers can
 * catch all Unitpay-originated failures at once. Each concrete class also extends
 * the SPL exception it historically threw, so existing
 * catch (InvalidArgumentException | UnexpectedValueException) blocks keep working.
 */
interface UnitpayExceptionInterface
{
}

/** Webhook signature did not match (possible forgery). */
class UnitpaySignatureException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** Webhook arrived from an IP outside the allowlist. */
class UnitpayIpException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** api() could not obtain a usable response from Unitpay (network or decoding). */
class UnitpayTransportException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** A method name is not supported by api() or the handler. */
class UnitpayUnsupportedMethodException extends UnexpectedValueException implements UnitpayExceptionInterface
{
}

/** A caller supplied an invalid or missing argument (bad param, missing secret, malformed input). */
class UnitpayValidationException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/**
 * Value object for paid goods
 */
final class CashItem
{
    /** Без НДС */
    public const NDS_NONE = 'none';
    /** НДС по ставке 0% */
    public const NDS_0 = 'vat0';
    /** НДС по ставке 5% */
    public const NDS_5 = 'vat5';
    /** НДС по ставке 7% */
    public const NDS_7 = 'vat7';
    /** НДС по ставке 10% */
    public const NDS_10 = 'vat10';
    /**
     * НДС по ставке 20%.
     * Внимание: с 2026 г. бэкенд формирует по этому значению чек с НДС 22%
     * (повышение основной ставки НДС). Отдельного пути для «настоящих» 20%
     * на бэкенде нет — vat20 и vat22 отображаются в один фискальный код.
     */
    public const NDS_20 = 'vat20';
    /** НДС по ставке 22% */
    public const NDS_22 = 'vat22';
    /** НДС по расчётной ставке 5/105 */
    public const NDS_105 = 'vat105';
    /** НДС по расчётной ставке 7/107 */
    public const NDS_107 = 'vat107';
    /** НДС по расчётной ставке 10/110 */
    public const NDS_110 = 'vat110';
    /** НДС по расчётной ставке 20/120 */
    public const NDS_120 = 'vat120';
    /** НДС по расчётной ставке 22/122 */
    public const NDS_122 = 'vat122';

    /** Товар */
    public const PAYMENT_OBJECT_COMMODITY = 'commodity';
    /** Работа */
    public const PAYMENT_OBJECT_JOB = 'job';
    /** Услуга */
    public const PAYMENT_OBJECT_SERVICE = 'service';
    /** Лотерейный билет */
    public const PAYMENT_OBJECT_LOTTERY = 'lottery';
    /** Результаты интеллектуальной деятельности */
    public const PAYMENT_OBJECT_INTELLECTUAL_ACTIVITY = 'intellectual_activity';
    /** Платёж (аванс, задаток, предоплата, кредит) */
    public const PAYMENT_OBJECT_PAYMENT = 'payment';
    /** Агентское вознаграждение */
    public const PAYMENT_OBJECT_AGENT_COMMISSION = 'agent_commission';
    /** Взнос, пеня, штраф, бонус и иной аналогичный предмет расчёта */
    public const PAYMENT_OBJECT_PAYMENT_2 = 'payment_2';
    /** Иной предмет расчёта */
    public const PAYMENT_OBJECT_ANOTHER = 'another';
    /** Имущественное право */
    public const PAYMENT_OBJECT_PROPERTY_RIGHT = 'property_right';
    /** Внереализационный доход */
    public const PAYMENT_OBJECT_NON_OPERATING_GAIN = 'non-operating_gain';
    /** Страховые взносы */
    public const PAYMENT_OBJECT_INSURANCE_PREMIUM = 'insurance_premium';
    /** Торговый сбор */
    public const PAYMENT_OBJECT_SALES_TAX = 'sales_tax';
    /** Курортный сбор */
    public const PAYMENT_OBJECT_RESORT_FEE = 'resort_fee';
    /** Залог */
    public const PAYMENT_OBJECT_DEPOSIT = 'deposit';
    /** Расход */
    public const PAYMENT_OBJECT_EXPENSE = 'expense';
    /** Взносы на обязательное пенсионное страхование ИП */
    public const PAYMENT_OBJECT_PENSION_INSURANCE_IP = 'pension_insurance_ip';
    /** Взносы на обязательное пенсионное страхование */
    public const PAYMENT_OBJECT_PENSION_INSURANCE = 'pension_insurance';
    /** Взносы на обязательное медицинское страхование ИП */
    public const PAYMENT_OBJECT_MEDICAL_INSURANCE_IP = 'medical_insurance_ip';
    /** Взносы на обязательное медицинское страхование */
    public const PAYMENT_OBJECT_MEDICAL_INSURANCE = 'medical_insurance';
    /** Взносы на обязательное социальное страхование */
    public const PAYMENT_OBJECT_SOCIAL_INSURANCE = 'social_insurance';
    /** Платёж казино */
    public const PAYMENT_OBJECT_CASINO_PAYMENT = 'casino_payment';
    /** Выдача денежных средств */
    public const PAYMENT_OBJECT_ISSUANCE_BANK = 'issuance_bank';
    /** Товар, подлежащий маркировке, без кода маркировки */
    public const PAYMENT_OBJECT_COMMODITY_WITHOUT_MARK = 'commodity_without_mark';
    /** Товар, подлежащий маркировке, с кодом маркировки */
    public const PAYMENT_OBJECT_COMMODITY_MARK = 'commodity_mark';

    // Отклоняются текущим публичным API. Оставлены ради обратной совместимости
    // и будут удалены в 3.0 — не используйте в новом коде.
    /** @deprecated Отклоняется публичным API; будет удалено в 3.0. */
    public const PAYMENT_OBJECT_EXCISE = 'excise';
    /** @deprecated Отклоняется публичным API; будет удалено в 3.0. */
    public const PAYMENT_OBJECT_GAMBLING_BET = 'gambling_bet';
    /** @deprecated Отклоняется публичным API; будет удалено в 3.0. */
    public const PAYMENT_OBJECT_GAMBLING_PRIZE = 'gambling_prize';
    /** @deprecated Отклоняется публичным API; будет удалено в 3.0. */
    public const PAYMENT_OBJECT_LOTTERY_PRIZE = 'lottery_prize';
    /** @deprecated Отклоняется публичным API; будет удалено в 3.0. */
    public const PAYMENT_OBJECT_COMPOSITE = 'composite';

    /** 100% предоплата */
    public const PAYMENT_METHOD_PREPAYMENT_FULL = 'full_prepayment';
    /** Частичная предоплата */
    public const PAYMENT_METHOD_PREPAYMENT = 'prepayment';
    /** Аванс */
    public const PAYMENT_METHOD_ADVANCE = 'advance';
    /** Полный расчёт */
    public const PAYMENT_METHOD_PAYMENT_FULL = 'full_payment';

    /** Штука, единица */
    public const MEASURE_ITEM = 0;
    /** Грамм */
    public const MEASURE_G = 10;
    /** Килограмм */
    public const MEASURE_KG = 11;
    /** Тонна */
    public const MEASURE_T = 12;
    /** Сантиметр */
    public const MEASURE_CM = 20;
    /** Дециметр */
    public const MEASURE_DM = 21;
    /** Метр */
    public const MEASURE_M = 22;
    /** Квадратный сантиметр */
    public const MEASURE_CM2 = 30;
    /** Квадратный дециметр */
    public const MEASURE_DM2 = 31;
    /** Квадратный метр */
    public const MEASURE_M2 = 32;
    /** Миллилитр */
    public const MEASURE_ML = 40;
    /** Литр */
    public const MEASURE_L = 41;
    /** Кубический метр */
    public const MEASURE_M3 = 42;
    /** Киловатт-час */
    public const MEASURE_KWH = 50;
    /** Гигакалория */
    public const MEASURE_GC = 51;
    /** Сутки (день) */
    public const MEASURE_D = 70;
    /** Час */
    public const MEASURE_H = 71;
    /** Минута */
    public const MEASURE_MIN = 72;
    /** Секунда */
    public const MEASURE_S = 73;
    /** Килобайт */
    public const MEASURE_KB = 80;
    /** Мегабайт */
    public const MEASURE_MB = 81;
    /** Гигабайт */
    public const MEASURE_GB = 82;
    /** Терабайт */
    public const MEASURE_TB = 83;
    /** Иная единица измерения */
    public const MEASURE_OTHER = 255;

    private $name;
    private $count;
    private $price;
    private $nds;
    private $type;
    private $paymentMethod;
    private $sum;
    private $currency;
    private $measure;
    private $nomenclatureCode;
    private $markCode;
    private $markQuantity;
    private $preText;
    private $postText;

    /**
     * @param string $name
     * @param int|float|string $count positive quantity (fractional allowed for weight/volume)
     * @param float|int|string $price non-negative price per unit
     * @param string $nds
     * @param string $type
     * @param string $paymentMethod
     */
    public function __construct(
        $name,
        $count,
        $price,
        $nds = self::NDS_NONE,
        $type = self::PAYMENT_OBJECT_COMMODITY,
        $paymentMethod = self::PAYMENT_METHOD_PREPAYMENT_FULL
    ) {
        // is_numeric first: on PHP 8 "abc" < 1 is a string comparison that yields
        // false, so a non-numeric value would otherwise slip past the range check.
        if (!is_numeric($count) || $count <= 0) {
            throw new UnitpayValidationException('CashItem count must be a positive number');
        }
        if (!is_numeric($price) || $price < 0) {
            throw new UnitpayValidationException('CashItem price must be a non-negative number');
        }
        $this->name = $name;
        // Keep the numeric value as-is (int or float): fractional quantities are valid
        // for weight/volume goods (MEASURE_KG/G/L, ...) and the backend rounds count to
        // 3 decimals, so truncating to int would silently corrupt the receipt.
        $this->count = $count + 0;
        $this->price = (float) $price;
        $this->nds = $nds;
        $this->type = $type;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @return string
     */
    public function getName()
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

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return string
     */
    public function getNds()
    {
        return $this->nds;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Итоговая сумма позиции. Если не задана, бэкенд считает её как price * count.
     * Не может превышать round(price * count, 2).
     * @param float $sum
     * @return $this
     */
    public function setSum($sum)
    {
        $this->sum = $sum;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getSum()
    {
        return $this->sum;
    }

    /**
     * Валюта позиции (ISO 4217). По умолчанию на бэкенде RUB.
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Единица измерения, одна из констант MEASURE_*.
     * @param int $measure
     * @return $this
     */
    public function setMeasure($measure)
    {
        $this->measure = $measure;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMeasure()
    {
        return $this->measure;
    }

    /**
     * Код товарной номенклатуры (маркировка).
     * @param string $nomenclatureCode
     * @return $this
     */
    public function setNomenclatureCode($nomenclatureCode)
    {
        $this->nomenclatureCode = $nomenclatureCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getNomenclatureCode()
    {
        return $this->nomenclatureCode;
    }

    /**
     * Код маркировки товара.
     * @param string $markCode
     * @return $this
     */
    public function setMarkCode($markCode)
    {
        $this->markCode = $markCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMarkCode()
    {
        return $this->markCode;
    }

    /**
     * Дробное количество маркированного товара.
     * Допускается только при measure = MEASURE_ITEM и count = 1.
     * @param int $numerator   числитель
     * @param int $denominator знаменатель
     * @return $this
     */
    public function setMarkQuantity($numerator, $denominator)
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
     * @return array|null
     */
    public function getMarkQuantity()
    {
        return $this->markQuantity;
    }

    /**
     * Текст перед позицией в чеке.
     * @param string $preText
     * @return $this
     */
    public function setPreText($preText)
    {
        $this->preText = $preText;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPreText()
    {
        return $this->preText;
    }

    /**
     * Текст после позиции в чеке.
     * @param string $postText
     * @return $this
     */
    public function setPostText($postText)
    {
        $this->postText = $postText;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPostText()
    {
        return $this->postText;
    }
}

/**
 * IP allowlist matcher: exact addresses and CIDR subnets (IPv4 and IPv6).
 * Extracted from UnitPay so the range-matching logic stays cohesive and testable.
 */
final class UnitpayIpAllowlist
{
    private $entries;

    /**
     * @param string[] $entries exact IPs and/or CIDR ranges (e.g. "77.75.153.0/25")
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function contains($ip)
    {
        // Convert the client IP to its packed form once, not once per CIDR entry.
        $ipBin = $this->toBinary($ip);
        foreach ($this->entries as $entry) {
            if (strpos($entry, '/') === false) {
                if ($entry === $ip) {
                    return true;
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
     * @param string $cidr
     * @param string $ipBin packed in_addr of the client IP (from toBinary())
     * @return bool
     */
    private function cidrContains($cidr, $ipBin)
    {
        // Only entries containing '/' reach this method, so explode() always yields
        // exactly two elements — no array_pad default is needed.
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
     * Whether $entry is a well-formed allowlist entry: an exact IPv4/IPv6 address
     * or an "address/bits" CIDR range. Used to validate a fetched IP feed before
     * it replaces the built-in list, so malformed JSON cannot empty the allowlist.
     * @param string $entry
     * @return bool
     */
    public static function isValidEntry($entry)
    {
        if (strpos($entry, '/') === false) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        list($subnet, $bits) = explode('/', $entry, 2);
        return ctype_digit($bits) && filter_var($subnet, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Parse the published webhook IP feed body ({"webhooks":[...]}) into a
     * validated, de-duplicated list of allowlist entries. Returns null on empty
     * input, malformed JSON, a missing or non-array "webhooks" key, or when no
     * entry is a well-formed IP/CIDR — so a bad feed can never empty the allowlist.
     * @param string $body
     * @return string[]|null
     */
    public static function parseWebhooksFeed($body)
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
     * @param string $ip
     * @return string|null packed in_addr, or null when $ip is not a valid address
     */
    private function toBinary($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $binary = inet_pton($ip);
        return $binary === false ? null : $binary;
    }

    /**
     * @param string $ipBin
     * @param string $subnetBin
     * @param int $bits
     * @return bool
     */
    private function prefixMatches($ipBin, $subnetBin, $bits)
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
 * Payment method Unitpay process
 */
class UnitPay
{
    // Коды способов оплаты для параметра `paymentType` в api('initPayment', ...)
    // и выплатах api('massPayment', ...). Источник истины — бэкенд; список кодов:
    // https://help.unitpay.ru/book-of-reference/payment-system-codes
    // paymentType здесь НЕ валидируется по этим значениям (как и словари CashItem),
    // поэтому новый код оплаты не требует релиза SDK — константы дают лишь
    // защиту от опечаток и автодополнение.
    /** Пластиковые карты (приём по картам всего мира) */
    public const PAYMENT_TYPE_CARD = 'card';
    /** Зарубежные карты через форму банка-эквайера */
    public const PAYMENT_TYPE_CARD_INVOICE = 'cardInvoice';
    /** Система быстрых платежей (СБП) */
    public const PAYMENT_TYPE_SBP = 'sbp';
    /** SberPay */
    public const PAYMENT_TYPE_SBERPAY = 'sberpay';
    /** Tinkoff Pay */
    public const PAYMENT_TYPE_TINKOFFPAY = 'tinkoffpay';
    /** PayPal */
    public const PAYMENT_TYPE_PAYPAL = 'paypal';
    /** WebMoney (WMZ-кошельки) */
    public const PAYMENT_TYPE_WEBMONEY = 'webmoney';

    // The supported api() methods are exactly the keys of this map; secretKey is
    // injected and validated by api(), so it is not listed among the required params.
    private $requiredUnitpayMethodsParams = [
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
    // Webhook methods Unitpay sends to the handler. 'preauth' is the two-stage
    // hold notification (funds blocked, not yet captured) — see payment-handler
    // docs; it must verify like the others, not be rejected as unsupported.
    private $supportedPartnerMethods = ['check', 'pay', 'preauth', 'error'];
    // Unitpay's published outbound IPs. 127.0.0.1 is deliberately NOT here: behind a
    // same-host reverse proxy REMOTE_ADDR is 127.0.0.1, which would make the IP gate a
    // no-op. Add it explicitly via setAllowedIps() for local debugging only.
    private $supportedUnitpayIp = [
        '31.186.100.49',
        '51.250.20.9',
    ];

    private $secretKey;
    private $params = [];
    private $apiUrl;
    private $formUrl;
    private $transport;
    private $request;
    private $clientIp;
    private $handlerMethod;
    private $handlerParams;
    private $ipAllowlist;
    // Merchant-specific IPs added via addAllowedIps(), always applied on top of the
    // Unitpay list and preserved across refreshAllowedIps()/setAllowedIps().
    private $customIps = [];
    private $ipsUrl;

    /**
     * @param string $domain Host only, e.g. "unitpay.ru" — no scheme or path (it becomes "https://$domain/api").
     * @param string|null $secretKey
     * @param callable|null $transport Outbound HTTP transport used by api(): fn(string $url): string|false.
     *                                 Defaults to file_get_contents(). Inject to test api() without the network.
     * @param array|null $request      Inbound webhook request array read by checkHandlerRequest().
     *                                 Defaults to $_GET. Inject to test the handler without superglobals.
     * @param string|null $clientIp    Source IP used by getIp(). Defaults to $_SERVER['REMOTE_ADDR'].
     *                                 Inject to test the IP allowlist without superglobals.
     */
    public function __construct($domain, $secretKey = null, ?callable $transport = null, ?array $request = null, ?string $clientIp = null)
    {
        $this->secretKey = $secretKey;
        $this->apiUrl = "https://$domain/api";
        $this->formUrl = "https://$domain/pay/";
        $this->ipsUrl = "https://$domain/ips/ips_webhooks.json";
        $this->transport = $transport;
        $this->request = $request;
        $this->clientIp = $clientIp;
    }

    /**
     * Override the Unitpay IP addresses allowed to call the handler. Replaces the
     * built-in default (or a previously fetched list) entirely, but does NOT touch
     * the merchant IPs added via addAllowedIps(), which stay applied on top. Use it
     * to keep the SDK in sync when the Unitpay infrastructure changes without
     * waiting for a release, or to feed back a list you fetched and cached yourself.
     * @link https://help.unitpay.ru/book-of-reference/ip-addresses
     * @param string[] $ips
     * @return $this
     */
    public function setAllowedIps(array $ips)
    {
        $this->supportedUnitpayIp = $ips;
        $this->ipAllowlist = null; // rebuild the matcher lazily on the next check
        return $this;
    }

    /**
     * Add merchant-specific IPs/CIDR ranges (e.g. your own proxy/relay) on top of
     * the Unitpay list. Unlike setAllowedIps(), which replaces the Unitpay list,
     * these persist across refreshAllowedIps()/setAllowedIps() calls. De-duplicated.
     * @param string[] $ips exact IPs and/or CIDR ranges
     * @return $this
     */
    public function addAllowedIps(array $ips)
    {
        $this->customIps = array_values(array_unique(array_merge($this->customIps, $ips)));
        $this->ipAllowlist = null; // rebuild the matcher lazily on the next check
        return $this;
    }

    /**
     * Fetch Unitpay's currently published webhook IPs from
     * https://<domain>/ips/ips_webhooks.json and use them as the allowlist.
     *
     * Best-effort and fail-safe: on any transport/parse/validation failure the
     * previously configured Unitpay list (the built-in default, or whatever
     * setAllowedIps() last set) is kept unchanged — this never empties the list
     * and never throws, so it is safe to chain before checkHandlerRequest(). A
     * successful fetch REPLACES the Unitpay list (so a decommissioned IP drops
     * out); merchant IPs added via addAllowedIps() are preserved and always apply
     * on top.
     *
     * Verified TLS matters here (httpGet keeps CURLOPT_SSL_VERIFYPEER / verify_peer
     * on): an unverified or spoofed list would defeat the IP gate.
     *
     * This makes a blocking network request — call it periodically (e.g. a daily
     * cron) and cache getAllowedIps() on your side; do NOT call it on every webhook.
     * @return $this
     */
    public function refreshAllowedIps()
    {
        $ips = $this->fetchUnitpayIps();
        if ($ips !== null) {
            $this->supportedUnitpayIp = $ips;
            $this->ipAllowlist = null; // rebuild the matcher lazily on the next check
        }
        return $this;
    }

    /**
     * Effective allowlist actually enforced by the handler: the Unitpay list plus
     * the merchant additions, de-duplicated. Cache this after refreshAllowedIps()
     * and feed it back via setAllowedIps() on webhook requests to avoid a network
     * call per callback.
     * @return string[]
     */
    public function getAllowedIps()
    {
        return array_values(array_unique(array_merge($this->supportedUnitpayIp, $this->customIps)));
    }

    /**
     * Load and validate the published webhook IP feed.
     * @return string[]|null validated non-empty list, or null on any failure
     */
    private function fetchUnitpayIps()
    {
        $body = $this->httpGet($this->ipsUrl);
        return is_string($body) ? UnitpayIpAllowlist::parseWebhooksFeed($body) : null;
    }

    /**
     * Create SHA-256 digital signature
     * @param array $params
     * @param string|null $method
     * @return string
     */
    public function getSignature(array $params, $method = null)
    {
        // Strip caller-supplied signature keys and guard the auto-append index:
        // a crafted params[PHP_INT_MAX] would make the secretKey append below a
        // silent no-op, dropping the secret from the hash and making the
        // signature forgeable (bypass on PHP <8, fatal Error/DoS on PHP >=8).
        // Do NOT remove these unsets — regression previously introduced in 7835fb4.
        unset($params['sign'], $params['signature'], $params[PHP_INT_MAX]);
        ksort($params);
        $params[] = $this->secretKey;

        if ($method !== null) {
            array_unshift($params, $method);
        }

        // A crafted webhook can inject an array value (e.g. params[x][]=1); coerce
        // non-scalars to '' so implode() cannot emit an "Array to string conversion"
        // warning. The check still fails closed — the secret is appended regardless.
        $params = array_map(static function ($value) {
            if (is_float($value)) {
                return self::floatToString($value);
            }
            return is_scalar($value) ? $value : '';
        }, $params);

        return hash('sha256', implode('{up}', $params));
    }

    /**
     * Return IP address
     * @return string
     */
    protected function getIp()
    {
        return $this->clientIp !== null ? $this->clientIp : $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Whether $ip may call the handler. Matches exact addresses and CIDR subnets
     * (IPv4/IPv6) via UnitpayIpAllowlist, so setAllowedIps(['77.75.153.0/25'])
     * works (F024). Override for proxy-aware logic.
     * @param string $ip
     * @return bool
     */
    protected function isAllowedIp($ip)
    {
        if ($this->ipAllowlist === null) {
            $this->ipAllowlist = new UnitpayIpAllowlist(
                array_merge($this->supportedUnitpayIp, $this->customIps)
            );
        }
        return $this->ipAllowlist->contains($ip);
    }

    /**
     * Perform the outbound HTTP GET used by api().
     * Resolution order: injected $transport -> cURL (if ext-curl present) -> file_get_contents.
     * cURL adds connect/read timeouts and does not require allow_url_fopen; both fallbacks
     * carry a timeout too. Returns the response body, or false on a transport failure
     * (which api() turns into a "Temporary server error").
     * @param string $url
     * @return string|false
     */
    protected function httpGet($url)
    {
        if ($this->transport !== null) {
            return call_user_func($this->transport, $url);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                // CURLOPT_SSL_VERIFYPEER defaults to true — TLS verification stays on.
            ]);
            $body = curl_exec($ch);
            // On PHP >=8.0 curl_close() is a deprecated no-op (the handle is a
            // CurlHandle object freed by GC), so skip it to avoid the E_DEPRECATED
            // notice on 8.5. On PHP <8.0 the handle is a resource — close it
            // explicitly for deterministic cleanup (though refcounting would also
            // free it when $ch leaves scope on return).
            if (\PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            return $body;
        }

        $context = stream_context_create(['http' => ['timeout' => 10]]);
        // Swallow the transport warning without the '@' operator (the QA ruleset forbids
        // it): that warning would otherwise embed the secret-bearing URL in the error log.
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
     * Get URL for pay through the form
     * @param string $publicKey
     * @param string|float|int $sum
     * @param string $account
     * @param string $desc
     * @param string $currency
     * @param string $locale
     * @return string
     */
    public function form($publicKey, $sum, $account, $desc, $currency = 'RUB', $locale = 'ru')
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
        // Build the URL from a local array and consume the fluent-setter params: a
        // reused instance must not carry this call's params (or form()'s vital params
        // and signature) into the next form()/api() call.
        $params = array_merge($this->params, $vitalParams);
        $params['signature'] = $this->getSignature($vitalParams);
        $params['locale'] = $locale;
        $this->params = [];
        return $this->formUrl . $publicKey . '?' . http_build_query($params);
    }

    /**
     * Set customer email
     * @param string $email
     * @return $this
     */
    public function setCustomerEmail($email)
    {
        $this->params['customerEmail'] = $email;
        return $this;
    }

    /**
     * Set customer phone number
     * @param string $phone
     * @return $this
     */
    public function setCustomerPhone($phone)
    {
        $this->params['customerPhone'] = $phone;
        return $this;
    }

    /**
     * Set list of paid goods
     * @param CashItem[] $items
     * @return $this
     */
    public function setCashItems(array $items)
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

            // Optional fields: serialize only the ones that were actually set.
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
            // e.g. a non-UTF-8 (Windows-1251) product name — never ship an empty receipt.
            throw new UnitpayValidationException('Failed to encode cashItems: ' . json_last_error_msg());
        }
        $this->params['cashItems'] = base64_encode($json);

        return $this;
    }

    /**
     * Set callback URL
     * @param string $backUrl
     * @return $this
     */
    public function setBackUrl($backUrl)
    {
        $this->params['backUrl'] = $backUrl;
        return $this;
    }

    /**
     * Call API
     * @param string $method
     * @param array $params
     * @return object
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function api($method, array $params = [])
    {
        if (!isset($this->requiredUnitpayMethodsParams[$method])) {
            throw new UnitpayUnsupportedMethodException('Method is not supported');
        }

        // Fold in the fluent-setter params (setCashItems/setCustomerEmail/…) so
        // setCashItems()->api('initPayment', ...) sends the receipt. Only the setters
        // write to $this->params — form() builds its URL locally — so folding the whole
        // bucket is safe and no new setter can be silently dropped. Explicit $params win.
        // The bucket is consumed on success (below) so a reused instance never bleeds
        // one call's params into an unrelated next call.
        $params = array_merge($this->params, $params);

        foreach ($this->requiredUnitpayMethodsParams[$method] as $rParam) {
            if (!isset($params[$rParam])) {
                throw new UnitpayValidationException('Param ' . $rParam . ' is null');
            }
        }

        // The instance key is the default; an explicit NON-EMPTY secretKey in $params wins
        // so account-level methods (getPartner, getCommissions, payouts, ...) can use the
        // account key. A falsy value (e.g. an unset getenv()) falls back to the instance key.
        if (empty($params['secretKey'])) {
            $params['secretKey'] = $this->secretKey;
        }
        if (empty($params['secretKey'])) {
            throw new UnitpayValidationException('SecretKey is null');
        }

        $params = self::stringifyFloats($params);

        // Union (+) keeps 'method' authoritative if a param key collides with it.
        $requestUrl = $this->apiUrl . '?' . http_build_query(
            ['method' => $method] + $params,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $response = json_decode($this->httpGet($requestUrl));
        if (!is_object($response)) {
            throw new UnitpayTransportException('Temporary server error. Please try again later.');
        }

        // Consume the fluent-setter params only after a fully successful call, so a
        // failed or retried call keeps them, but the next unrelated call starts clean.
        $this->params = [];

        return $response;
    }

    /**
     * Check request on handler from Unitpay
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function checkHandlerRequest()
    {
        $ip = $this->getIp();
        if (empty($this->secretKey)) {
            throw new UnitpayValidationException('SecretKey is null');
        }

        $request = $this->request !== null ? $this->request : $_GET;

        if (!isset($request['method'])) {
            throw new UnitpayValidationException('Method is null');
        }

        if (!isset($request['params'])) {
            throw new UnitpayValidationException('Params is null');
        }

        list($method, $params) = [$request['method'], $request['params']];

        if (!in_array($method, $this->supportedPartnerMethods, true)) {
            throw new UnitpayUnsupportedMethodException('Method is not supported');
        }

        if (!isset($params['signature']) || !is_string($params['signature'])
            || !hash_equals($this->getSignature($params, $method), $params['signature'])) {
            throw new UnitpaySignatureException('Wrong signature');
        }

        /**
         * IP address check
         * @link https://help.unitpay.ru/book-of-reference/ip-addresses
         */
        if (!$this->isAllowedIp($ip)) {
            throw new UnitpayIpException('IP address Error');
        }

        // Expose the validated method/params so consumers read them from here
        // (works with an injected $request) instead of re-reading $_GET.
        $this->handlerMethod = $method;
        $this->handlerParams = $params;

        return true;
    }

    /**
     * Webhook method validated by the last successful checkHandlerRequest()
     * ('check' | 'pay' | 'error'). Read this instead of $_GET so an injected
     * request is honoured. Null before a successful validation.
     * @return string|null
     */
    public function getHandlerMethod()
    {
        return $this->handlerMethod;
    }

    /**
     * Webhook params validated by the last successful checkHandlerRequest().
     * Null before a successful validation.
     * @return array|null
     */
    public function getHandlerParams()
    {
        return $this->handlerParams;
    }

    /**
     * Render float params as locale-independent decimal strings so the signature
     * and the request URL stay identical on PHP <8.0 (where (string)$float honours
     * LC_NUMERIC and would emit "100,5" in comma-decimal locales). Non-floats pass through.
     * @param array $params
     * @return array
     */
    private static function stringifyFloats(array $params)
    {
        foreach ($params as $key => $value) {
            if (is_float($value)) {
                $params[$key] = self::floatToString($value);
            }
        }

        return $params;
    }

    /**
     * Render a float as a locale-independent decimal string with no trailing zeros.
     * (string) $float honours LC_NUMERIC on PHP <8.0 and would emit "100,5" in
     * comma-decimal locales, breaking signature/URL consistency. Shared by
     * getSignature() and stringifyFloats() so both sign and transmit the same form.
     * @param float $value
     * @return string
     */
    private static function floatToString($value)
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
    }

    /**
     * Response for Unitpay if handle success
     * @param string $message
     * @return string
     */
    public function getSuccessHandlerResponse($message)
    {
        return json_encode(['result' => ['message' => $message]]);
    }

    /**
     * Response for Unitpay if handle error
     * @param string $message
     * @return string
     */
    public function getErrorHandlerResponse($message)
    {
        return json_encode(['error' => ['message' => $message]]);
    }
}
