<?php /** @noinspection PhpUnused */

/**
 * UnitPay Payment Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category        UnitPay
 * @package         unitpay/unitpay
 * @version         3.0.0
 * @author          UnitPay
 * @copyright       Copyright (c) 2020 UnitPay
 * @license         http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * EXTENSION INFORMATION
 *
 * UNITPAY API       https://unitpay.ru/doc
 *
 */

declare(strict_types=1);

namespace UnitPay;

use InvalidArgumentException;
use UnexpectedValueException;
use UnitPay\API\API;
use UnitPay\Exceptions\UnsupportedDomain;
use UnitPay\Exceptions\WrongIpAddress;

/**
 * Class UnitPay
 *
 * @package     UnitPay
 *
 * @author      UnitPay <support@unitpay.ru>
 * @author      Alexander Gorenkov <g.a.androidjc2@ya.ru> <Tg:@alex_brin>
 * @version     3.0.0
 * @since       1.0.0
 */
class UnitPay
{
    #region Constants

    public const SUPPORTED_CURRENCIES = ['EUR', 'UAH', 'BYR', 'USD', 'RUB'];
    public const SUPPORTED_PARTNER_METHODS = ['check', 'pay', 'error'];

    public const DOMAIN_RU = 'unitpay.ru';
    public const DOMAIN_MONEY = 'unitpay.money';
    public const ALLOWED_DOMAINS = [
        self::DOMAIN_RU, self::DOMAIN_MONEY,
    ];

    public const UNITPAY_IPS = [
        '51.250.20.9',
        '31.186.100.49',
        '178.132.203.105',
        '52.29.152.23',
        '52.19.56.234',
        '127.0.0.1' // for debug
    ];

    #endregion Constants

    #region Properties

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var string|null
     */
    private $secretKey;

    /**
     * @var string|null
     */
    private $secretPersonalKey;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $formUrl;

    /**
     * @var mixed[]
     */
    private $params = [];

    #endregion Properties

    /**
     * UnitPay constructor.
     *
     * @param  string  $publicKey
     * @param  string|null  $secretKey
     * @param  string|null  $secretPersonalKey
     * @param  string  $domain
     *
     * @throws UnsupportedDomain
     */
    public function __construct(string $publicKey, ?string $secretKey = null, ?string $secretPersonalKey = null, string $domain = self::DOMAIN_MONEY)
    {
        if (!in_array($domain, self::ALLOWED_DOMAINS)) {
            throw new UnsupportedDomain(sprintf("`%s` is unsupported domain. Allowed: %s", $domain, implode(', ', self::ALLOWED_DOMAINS)));
        }

        $this->publicKey         = $publicKey;
        $this->secretKey         = $secretKey;
        $this->secretPersonalKey = $secretPersonalKey;
        $this->apiUrl            = sprintf("https://%s/api", $domain);
        $this->formUrl           = sprintf("https://%s/pay", $domain);
    }

    public function getAPI(): API
    {
        return new API($this->apiUrl, $this->secretKey, $this->secretPersonalKey);
    }

    /**
     * @param  array  $params
     * @param  string|null  $method
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getSignature(array $params, ?string $method = null): string
    {
        unset($params['sign'], $params['signature']);

        ksort($params);

        $params[] = $this->secretKey;

        if ($method) {
            array_unshift($params, $method);
        }

        return hash('sha256', implode('{up}', $params));
    }

    /**
     * @param  float  $sum
     * @param  string|int  $account
     * @param  string  $desc
     * @param  string  $currency
     * @param  string  $locale
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function form(float $sum, $account, string $desc, string $currency = 'RUB', string $locale = 'ru'): string
    {
        $vitalParams = [
            'account'  => $account,
            'currency' => $currency,
            'desc'     => $desc,
            'sum'      => $sum,
        ];

        $params = array_merge($this->params, $vitalParams);

        if (!empty($this->secretKey)) {
            $params['signature'] = $this->getSignature($vitalParams);
        }

        $params['locale'] = $locale;

        return sprintf("%s/%s?%s", $this->formUrl, $this->publicKey, http_build_query($params));
    }

    /**
     * Check request on handler from UnitPay
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws WrongIpAddress
     */
    public function handleRequest(): bool
    {
        if (!isset($_GET['method'])) {
            throw new InvalidArgumentException('Empty method');
        }

        if (!isset($_GET['params']) || empty($_GET['params'])) {
            throw new InvalidArgumentException('Empty params');
        }

        [$method, $params] = [$_GET['method'], $_GET['params']];

        if (!in_array($method, self::SUPPORTED_PARTNER_METHODS, true)) {
            throw new UnexpectedValueException('Method is not supported');
        }

        if (!isset($params['signature']) || $params['signature'] !== $this->getSignature($params, $method)) {
            throw new InvalidArgumentException('Wrong signature');
        }

        /**
         * IP address check
         *
         * @link http://help.unitpay.ru/article/67-ip-addresses
         * @link http://help.unitpay.money/article/67-ip-addresses
         */
        if (!in_array($this->getRealIP(), self::UNITPAY_IPS, true)) {
            throw new WrongIpAddress('IP address Error');
        }

        return true;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     * @since 3.0.0 Returns the real ip when using cloudflare/etc.
     */
    public function getRealIP(): string
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        $forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $ip      = $_SERVER['REMOTE_ADDR'];

        if (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        }

        return $ip;
    }

    #region UnitPay response

    /**
     * Response for UnitPay if handle success
     *
     * @param  string  $message
     *
     * @return string
     */
    public function getSuccessHandlerResponse(string $message): string
    {
        return json_encode([
            "result" => [
                "message" => $message,
            ],
        ]);
    }

    /**
     * Response for UnitPay if handle error
     *
     * @param  string  $message
     *
     * @return string
     */
    public function getErrorHandlerResponse(string $message): string
    {
        return json_encode([
            "error" => [
                "message" => $message,
            ],
        ]);
    }

    #endregion UnitPay response

    #region Getters and setters

    /**
     * Set list of paid goods
     *
     * @param  CashItem[]  $items
     *
     * @return UnitPay
     */
    public function setCashItems(array $items): self
    {
        $this->params['cashItems'] = base64_encode(
            json_encode(
                array_map(static function ($item) {
                    /** @var CashItem $item */
                    return [
                        'name'          => $item->getName(),
                        'count'         => $item->getCount(),
                        'price'         => $item->getPrice(),
                        'nds'           => $item->getNds(),
                        'type'          => $item->getType(),
                        'paymentMethod' => $item->getPaymentMethod(),
                    ];
                }, $items)));

        return $this;
    }

    /**
     * Set callback URL
     *
     * @param  string  $backUrl
     *
     * @return UnitPay
     */
    public function setBackUrl(string $backUrl): self
    {
        $this->params['backUrl'] = $backUrl;

        return $this;
    }

    public function setCustomerPhone(string $phone): self
    {
        $this->params['customerPhone'] = $phone;

        return $this;
    }

    public function setCustomerEmail(string $email): self
    {
        $this->params['customerEmail'] = $email;

        return $this;
    }

    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    #endregion Getters and setters
}