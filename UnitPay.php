<?php

/**
 * Маркерный интерфейс, который реализуют все исключения этого SDK, чтобы вызывающий
 * код мог перехватывать любые ошибки Unitpay одним catch. Каждый конкретный класс
 * дополнительно наследует то SPL-исключение, которое исторически выбрасывалось,
 * поэтому существующие блоки
 * catch (InvalidArgumentException | UnexpectedValueException) продолжают работать.
 */
interface UnitpayExceptionInterface
{
}

/** Подпись вебхука не совпала (возможна подделка). */
class UnitpaySignatureException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** Вебхук пришёл с IP-адреса вне белого списка. */
class UnitpayIpException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** api() не смог получить пригодный ответ от Unitpay (сеть или разбор ответа). */
class UnitpayTransportException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/** Метод не поддерживается api() или обработчиком. */
class UnitpayUnsupportedMethodException extends UnexpectedValueException implements UnitpayExceptionInterface
{
}

/** Передан некорректный или отсутствующий аргумент (неверный параметр, нет ключа, некорректные данные). */
class UnitpayValidationException extends InvalidArgumentException implements UnitpayExceptionInterface
{
}

/**
 * Неизменяемый объект-значение одной позиции фискального чека (54-ФЗ).
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
     * $count и $price проверяются через is_numeric() ДО проверки диапазона: в PHP 8
     * сравнение нечисловой строки с числом ("abc" <= 0) выполняется как сравнение
     * строк и даёт false, поэтому непроверенное значение прошло бы как корректное.
     * $count сохраняется как есть (int или float): дробные количества допустимы для
     * весовых/объёмных товаров (MEASURE_KG/G/L, ...), а бэкенд округляет количество до
     * 3 знаков, поэтому приведение к int тихо испортило бы чек.
     *
     * @param string $name
     * @param int|float|string $count положительное количество (дробное допустимо для веса/объёма)
     * @param float|int|string $price неотрицательная цена за единицу
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
 * Проверяет, входит ли IP в белый список: точные адреса и CIDR-подсети
 * (IPv4 и IPv6). Вынесен из UnitPay в отдельный класс, чтобы логика
 * сопоставления диапазонов оставалась связной и тестируемой.
 */
final class UnitpayIpAllowlist
{
    private $entries;

    /**
     * @param string[] $entries точные IP и/или CIDR-диапазоны (например, "77.75.153.0/25")
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
        $ipBin = $this->toBinary($ip);
        foreach ($this->entries as $entry) {
            if (strpos($entry, '/') === false) {
                if ($entry === $ip) {
                    return true;
                }
                // Нормализованное сравнение: один и тот же адрес в разной записи
                // (регистр/сжатие IPv6) даёт одинаковый упакованный in_addr.
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
     * @param string $cidr
     * @param string $ipBin упакованный in_addr клиентского IP (из toBinary())
     * @return bool
     */
    private function cidrContains($cidr, $ipBin)
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
     * Является ли $entry корректной записью белого списка: точным адресом IPv4/IPv6
     * или CIDR-диапазоном вида "адрес/биты". Используется для проверки загруженного
     * списка IP до того, как он заменит встроенный, чтобы некорректный JSON не мог
     * опустошить белый список.
     * @param string $entry
     * @return bool
     */
    public static function isValidEntry($entry)
    {
        if (strpos($entry, '/') === false) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        list($subnet, $bits) = explode('/', $entry, 2);
        if (!ctype_digit($bits) || filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // Длина префикса не может превышать разрядность адреса (IPv4 = 32, IPv6 = 128),
        // иначе запись валидна на вид, но не матчит ничего (prefixMatches вернёт false).
        $maxBits = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
        return (int) $bits <= $maxBits;
    }

    /**
     * Разбирает тело опубликованного перечня IP вебхуков ({"webhooks":[...]}) в
     * проверенный список записей без дубликатов. Возвращает null при пустом вводе,
     * некорректном JSON, отсутствующем или не-массивном ключе "webhooks" либо когда
     * ни одна запись не является корректным IP/CIDR — так плохой перечень не может
     * опустошить белый список.
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
     * @return string|null упакованный in_addr, либо null, если $ip не является корректным адресом
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
 * Клиент платёжного REST API Unitpay: подпись и построение формы/URL,
 * server-to-server вызовы API и проверка входящих вебхуков.
 */
class UnitPay
{
    /**
     * Коды способов оплаты для параметра `paymentType` в api('initPayment', ...)
     * и выплатах api('massPayment', ...). Источник истины — бэкенд, список кодов:
     * https://help.unitpay.ru/book-of-reference/payment-system-codes
     * paymentType по этим значениям НЕ валидируется (как и словари CashItem), поэтому
     * новый код оплаты не требует релиза SDK — константы дают лишь защиту от опечаток
     * и автодополнение.
     */
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

    /**
     * Поддерживаемые методы api() и их обязательные параметры. secretKey
     * подставляется и проверяется в api(), поэтому здесь не перечислен.
     */
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
    /**
     * Методы вебхуков, которые Unitpay шлёт обработчику. 'preauth' — уведомление о
     * двухстадийной блокировке средств (деньги заблокированы, но ещё не списаны):
     * должно проходить проверку как остальные, а не отклоняться как неподдерживаемое.
     */
    private $supportedPartnerMethods = ['check', 'pay', 'preauth', 'error'];
    /**
     * Опубликованные исходящие IP Unitpay. 127.0.0.1 здесь намеренно НЕТ: за
     * обратным прокси на том же хосте REMOTE_ADDR равен 127.0.0.1, что превратило бы
     * проверку IP в фикцию. Добавляйте его явно через setAllowedIps() только для
     * локальной отладки.
     */
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
    /**
     * IP самого мерчанта, добавленные через addAllowedIps(); всегда применяются
     * поверх списка Unitpay и сохраняются при refreshAllowedIps()/setAllowedIps().
     */
    private $customIps = [];
    private $ipsUrl;

    /**
     * @param string $domain только хост, например "unitpay.ru" — без схемы и пути (станет "https://$domain/api").
     * @param string|null $secretKey
     * @param callable|null $transport исходящий HTTP-транспорт для api(): fn(string $url): string|false.
     *                                 По умолчанию file_get_contents(). Подменяйте, чтобы тестировать api() без сети.
     * @param array|null $request      массив входящего вебхука, читаемый checkHandlerRequest().
     *                                 По умолчанию $_GET. Подменяйте, чтобы тестировать обработчик без суперглобальных переменных.
     * @param string|null $clientIp    IP отправителя, используемый getIp(). По умолчанию $_SERVER['REMOTE_ADDR'].
     *                                 Подменяйте, чтобы тестировать белый список IP без суперглобальных переменных.
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
     * Переопределяет список IP Unitpay, которым разрешено вызывать обработчик.
     * Полностью заменяет встроенный список по умолчанию (или ранее загруженный), но
     * НЕ трогает IP мерчанта, добавленные через addAllowedIps(), — они остаются
     * поверх. Используйте, чтобы держать SDK в актуальном состоянии при смене
     * инфраструктуры Unitpay, не дожидаясь релиза, или чтобы вернуть список, который
     * вы загрузили и закэшировали сами.
     * @link https://help.unitpay.ru/book-of-reference/ip-addresses
     * @param string[] $ips
     * @return $this
     */
    public function setAllowedIps(array $ips)
    {
        $this->supportedUnitpayIp = $ips;
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Добавляет IP/CIDR-диапазоны самого мерчанта (например, ваш прокси/релей)
     * поверх списка Unitpay. В отличие от setAllowedIps(), который заменяет список
     * Unitpay, эти сохраняются при вызовах refreshAllowedIps()/setAllowedIps().
     * Дубликаты убираются.
     * @param string[] $ips точные IP и/или CIDR-диапазоны
     * @return $this
     */
    public function addAllowedIps(array $ips)
    {
        $this->customIps = array_values(array_unique(array_merge($this->customIps, $ips)));
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Загружает актуальные опубликованные IP вебхуков Unitpay с
     * https://<domain>/ips/ips_webhooks.json и делает их белым списком.
     *
     * Действует по возможности и безопасно для сбоев: при любой ошибке
     * транспорта/разбора/проверки ранее настроенный список Unitpay (встроенный по
     * умолчанию или заданный последним setAllowedIps()) остаётся без изменений —
     * метод никогда не опустошает список и не бросает исключений, поэтому его
     * безопасно вызывать в цепочке перед checkHandlerRequest(). Успешная загрузка
     * ЗАМЕНЯЕТ список Unitpay (так выведенный из эксплуатации IP исчезает); IP
     * мерчанта, добавленные через addAllowedIps(), сохраняются и всегда применяются
     * поверх.
     *
     * Проверка TLS здесь важна (httpGet оставляет CURLOPT_SSL_VERIFYPEER / verify_peer
     * включёнными): непроверенный или подменённый список свёл бы на нет проверку IP.
     *
     * Метод делает блокирующий сетевой запрос — вызывайте его периодически (например,
     * ежедневным cron) и кэшируйте getAllowedIps() у себя; НЕ вызывайте его на каждый
     * вебхук.
     * @return $this
     */
    public function refreshAllowedIps()
    {
        $ips = $this->fetchUnitpayIps();
        if ($ips !== null) {
            $this->supportedUnitpayIp = $ips;
            $this->ipAllowlist = null;
        }
        return $this;
    }

    /**
     * Итоговый белый список, реально применяемый обработчиком: список Unitpay плюс
     * добавления мерчанта, без дубликатов. Кэшируйте его после refreshAllowedIps() и
     * возвращайте через setAllowedIps() при обработке вебхуков, чтобы не делать
     * сетевой запрос на каждый вызов.
     * @return string[]
     */
    public function getAllowedIps()
    {
        return array_values(array_unique(array_merge($this->supportedUnitpayIp, $this->customIps)));
    }

    /**
     * Загружает и проверяет опубликованный фид IP вебхуков.
     * @return string[]|null проверенный непустой список либо null при любой ошибке
     */
    private function fetchUnitpayIps()
    {
        $body = $this->httpGet($this->ipsUrl);
        return is_string($body) ? UnitpayIpAllowlist::parseWebhooksFeed($body) : null;
    }

    /**
     * Строит подпись SHA-256: значения параметров, отсортированные ksort и
     * объединённые буквальным разделителем "{up}", с $method в начале и secretKey в
     * конце.
     *
     * Безопасность: unset() убирает переданные вызывающим ключи подписи И индекс
     * PHP_INT_MAX — подделанный params[PHP_INT_MAX] превратил бы добавление secretKey
     * в пустую операцию, выкинув секрет из хэша и сделав подпись подделываемой (обход
     * на PHP <8, фатальная Error/DoS на PHP >=8). НЕ убирайте этот unset — защита
     * однажды была потеряна в 7835fb4 и восстановлена. Подделанный вебхук может также
     * подсунуть значение-массив (например, params[x][]=1), поэтому нескаляры
     * приводятся к '' — implode() не выдаёт предупреждение, а проверка всё равно
     * проваливается, потому что секрет добавляется в любом случае.
     *
     * @param array $params
     * @param string|null $method
     * @return string
     */
    public function getSignature(array $params, $method = null)
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
     * IP отправителя входящего запроса (подменённый clientIp либо $_SERVER['REMOTE_ADDR']).
     * @return string
     */
    protected function getIp()
    {
        return $this->clientIp !== null ? $this->clientIp : ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Разрешено ли $ip вызывать обработчик. Сопоставляет точные адреса и CIDR-подсети
     * (IPv4/IPv6) через UnitpayIpAllowlist, поэтому setAllowedIps(['77.75.153.0/25'])
     * работает. Переопределите для логики, учитывающей прокси.
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
     * Выполняет исходящий HTTP GET, используемый api().
     * Порядок выбора: подменённый $transport -> cURL (если есть ext-curl) -> file_get_contents.
     * cURL добавляет таймауты соединения/чтения и не требует allow_url_fopen; у обоих
     * запасных вариантов таймаут тоже есть. Возвращает тело ответа либо false при
     * ошибке транспорта (которую api() превращает в "Temporary server error").
     *
     * Безопасность: проверка TLS остаётся включённой (cURL сохраняет
     * CURLOPT_SSL_VERIFYPEER в значении по умолчанию true). Запасной вариант
     * file_get_contents гасит своё предупреждение транспорта через set_error_handler,
     * а не оператором '@' (который запрещён правилами QA) — иначе это предупреждение
     * записало бы в лог URL с секретом.
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
            ]);
            $body = curl_exec($ch);
            if (\PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            return $body;
        }

        $context = stream_context_create(['http' => ['timeout' => 10]]);
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
     * Строит URL-переход на размещённую у Unitpay платёжную форму. Параметры,
     * заданные fluent-сеттерами (setCashItems/setCustomerEmail/setBackUrl/...),
     * подмешиваются и затем очищаются, поэтому повторно используемый экземпляр не
     * переносит параметры этого вызова в следующий form()/api().
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
        $params = array_merge($this->params, $vitalParams);
        $params['signature'] = $this->getSignature($vitalParams);
        $params['locale'] = $locale;
        $this->params = [];
        return $this->formUrl . $publicKey . '?' . http_build_query($params);
    }

    /**
     * Задаёт email покупателя.
     * @param string $email
     * @return $this
     */
    public function setCustomerEmail($email)
    {
        $this->params['customerEmail'] = $email;
        return $this;
    }

    /**
     * Задаёт телефон покупателя.
     * @param string $phone
     * @return $this
     */
    public function setCustomerPhone($phone)
    {
        $this->params['customerPhone'] = $phone;
        return $this;
    }

    /**
     * Прикрепляет фискальный чек (позиции по 54-ФЗ) к следующему вызову form()/api().
     * Необязательные поля CashItem сериализуются только если заданы. Бросает
     * исключение вместо отправки пустого чека, если json_encode не удался (например,
     * имя не в UTF-8 / в Windows-1251).
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
     * Задаёт URL, на который Unitpay вернёт плательщика после оплаты.
     * @param string $backUrl
     * @return $this
     */
    public function setBackUrl($backUrl)
    {
        $this->params['backUrl'] = $backUrl;
        return $this;
    }

    /**
     * Выполняет server-to-server вызов REST API Unitpay. Параметры fluent-сеттеров
     * подмешиваются (чтобы setCashItems()->api('initPayment', ...) отправлял чек) и
     * очищаются только УСПЕШНЫМ вызовом. Сбой транспорта их сохраняет, чтобы повтор
     * ушёл с тем же чеком, — поэтому чистое состояние наступает лишь после успеха:
     * несвязанный вызов сразу ПОСЛЕ сбоя унаследует накопленные параметры (сбросьте их
     * явно или используйте новый экземпляр, если это нежелательно). Явные $params имеют
     * приоритет. Явный непустой secretKey в $params переопределяет ключ экземпляра,
     * поэтому методы уровня аккаунта (getPartner, getCommissions, выплаты, ...) могут
     * использовать ключ аккаунта.
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

        $params = array_merge($this->params, $params);

        foreach ($this->requiredUnitpayMethodsParams[$method] as $rParam) {
            if (!isset($params[$rParam])) {
                throw new UnitpayValidationException('Param ' . $rParam . ' is null');
            }
        }

        if (empty($params['secretKey'])) {
            $params['secretKey'] = $this->secretKey;
        }
        if (empty($params['secretKey'])) {
            throw new UnitpayValidationException('SecretKey is null');
        }

        $params = self::stringifyFloats($params);

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

        $this->params = [];

        return $response;
    }

    /**
     * Проверяет входящий вебхук: поддерживаемый метод, подпись SHA-256 (в постоянное
     * время) и белый список IP отправителя. При успехе выставляет проверенные метод и
     * параметры, доступные через getHandlerMethod()/getHandlerParams() (учитывая
     * подменённый запрос, а не $_GET).
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

        if (!$this->isAllowedIp($ip)) {
            throw new UnitpayIpException('IP address Error');
        }

        $this->handlerMethod = $method;
        $this->handlerParams = $params;

        return true;
    }

    /**
     * Метод вебхука, проверенный последним успешным checkHandlerRequest()
     * ('check' | 'pay' | 'preauth' | 'error'). Читайте его вместо $_GET, чтобы
     * учитывался подменённый запрос. До успешной проверки — null.
     * @return string|null
     */
    public function getHandlerMethod()
    {
        return $this->handlerMethod;
    }

    /**
     * Параметры вебхука, проверенные последним успешным checkHandlerRequest().
     * До успешной проверки — null.
     * @return array|null
     */
    public function getHandlerParams()
    {
        return $this->handlerParams;
    }

    /**
     * Приводит float-параметры к локале-независимым десятичным строкам, чтобы подпись
     * и URL запроса совпадали на PHP <8.0 (где (string)$float учитывает LC_NUMERIC и в
     * локалях с запятой выдал бы "100,5"). Значения, не являющиеся float, проходят как есть.
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
     * Приводит float к локале-независимой десятичной строке без хвостовых нулей.
     * (string) $float учитывает LC_NUMERIC на PHP <8.0 и в локалях с запятой выдал бы
     * "100,5", ломая совпадение подписи и URL. Используется совместно getSignature() и
     * stringifyFloats(), чтобы подпись и передаваемое значение имели одинаковый вид.
     * @param float $value
     * @return string
     */
    private static function floatToString($value)
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
    }

    /**
     * Строит JSON-ответ об успехе, который Unitpay ожидает от обработчика.
     * @param string $message
     * @return string
     */
    public function getSuccessHandlerResponse($message)
    {
        return json_encode(['result' => ['message' => $message]]);
    }

    /**
     * Строит JSON-ответ об ошибке, который Unitpay ожидает от обработчика.
     * @param string $message
     * @return string
     */
    public function getErrorHandlerResponse($message)
    {
        return json_encode(['error' => ['message' => $message]]);
    }
}
