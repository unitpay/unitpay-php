<?php

namespace Unitpay;

use Unitpay\Api\AbstractService;
use Unitpay\Api\PaymentService;
use Unitpay\Api\PayoutService;
use Unitpay\Api\PendingParams;
use Unitpay\Api\ReferenceService;
use Unitpay\Api\SubscriptionService;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\CurlTransport;
use Unitpay\Http\TransportInterface;
use Unitpay\Model\CashItem;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Webhook\WebhookVerifier;

/**
 * Thin client facade for the Unitpay payment REST API. Composition root: wires the
 * transport, signature builder and services, exposes the hosted-form builder and the
 * fluent setters, and hands out the API service objects and the webhook verifier.
 *
 * Server-to-server calls: $unitpay->payments()->initPayment(...), ->subscriptions(),
 * ->payouts(), ->reference(). Inbound webhooks: $unitpay->webhook()->checkHandlerRequest().
 */
final class Unitpay
{
    /** SDK version; sent in the telemetry fingerprint. Keep in sync with the release git tag. */
    public const VERSION = '4.0.0';

    /** Unitpay API surface this SDK targets; sent in the telemetry fingerprint. */
    public const API_VERSION = 'v1';

    private ?string $secretKey;
    private string $apiUrl;
    private string $formUrl;
    private TransportInterface $transport;
    private SignatureBuilder $signature;
    private PendingParams $pending;
    private WebhookVerifier $webhookVerifier;
    private ?PaymentService $payments = null;
    private ?SubscriptionService $subscriptions = null;
    private ?PayoutService $payouts = null;
    private ?ReferenceService $reference = null;

    /**
     * @param string $domain host only, e.g. "unitpay.ru" — without scheme or path,
     *                       optionally with a :port.
     * @param TransportInterface|null $transport outbound HTTP transport for api()/feed fetch.
     *                                 Defaults to CurlTransport. Inject a fake to test without the network.
     * @param array<string, mixed>|null $request inbound webhook array read by the webhook
     *                                   verifier. Defaults to $_GET.
     * @param string|null $clientIp sender IP used by the webhook verifier. Defaults to
     *                              $_SERVER['REMOTE_ADDR'].
     */
    public function __construct(
        string $domain,
        ?string $secretKey = null,
        ?TransportInterface $transport = null,
        ?array $request = null,
        ?string $clientIp = null
    ) {
        $this->assertValidDomain($domain);
        $this->secretKey = $secretKey;
        $this->apiUrl = "https://$domain/api";
        $this->formUrl = "https://$domain/pay/";
        $ipsUrl = "https://$domain/ips/ips_webhooks.json";
        $this->transport = $transport ?? new CurlTransport();
        $this->signature = new SignatureBuilder();
        $this->pending = new PendingParams();
        $this->webhookVerifier = new WebhookVerifier(
            $secretKey,
            $this->signature,
            $this->transport,
            $ipsUrl,
            $request,
            $clientIp
        );
    }

    /** Payment operations (initPayment, getPayment, refund, confirm/cancel, offsetAdvance). */
    public function payments(): PaymentService
    {
        return $this->payments = $this->payments ?? $this->makeService(PaymentService::class);
    }

    /** Subscription operations (list, get, close). */
    public function subscriptions(): SubscriptionService
    {
        return $this->subscriptions = $this->subscriptions ?? $this->makeService(SubscriptionService::class);
    }

    /** Mass-payout operations plus SBP bank list and BIN lookup (account-level). */
    public function payouts(): PayoutService
    {
        return $this->payouts = $this->payouts ?? $this->makeService(PayoutService::class);
    }

    /** Reference/account lookups (methods, commissions, currency rates, balance). */
    public function reference(): ReferenceService
    {
        return $this->reference = $this->reference ?? $this->makeService(ReferenceService::class);
    }

    /** The inbound webhook verifier (signature + IP allowlist, allowlist management). */
    public function webhook(): WebhookVerifier
    {
        return $this->webhookVerifier;
    }

    /**
     * Sets the URL Unitpay will return the payer to after payment.
     */
    public function setBackUrl(string $backUrl): self
    {
        $this->pending->set('backUrl', $backUrl);
        return $this;
    }

    /**
     * Sets the customer's email.
     */
    public function setCustomerEmail(string $email): self
    {
        $this->pending->set('customerEmail', $email);
        return $this;
    }

    /**
     * Sets the customer's phone.
     */
    public function setCustomerPhone(string $phone): self
    {
        $this->pending->set('customerPhone', $phone);
        return $this;
    }

    /**
     * Attaches a fiscal receipt (54-FZ line items) to the next form()/service call.
     * Optional CashItem fields are serialized only when set. Throws instead of sending
     * an empty receipt if json_encode fails (e.g. a name is not UTF-8 / is Windows-1251).
     * @param CashItem[] $items
     */
    public function setCashItems(array $items): self
    {
        $cashItems = array_map(static function (CashItem $item) {
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
        $this->pending->set('cashItems', base64_encode($json));

        return $this;
    }

    /**
     * Builds the redirect URL to Unitpay's hosted payment form. Accumulated fluent-setter
     * params are merged in and then cleared, so a reused instance does not carry this
     * call's parameters into the next form()/service call.
     * @param string|float|int $sum
     */
    public function form(string $publicKey, $sum, string $account, string $desc, string $currency = 'RUB', string $locale = 'ru'): string
    {
        if (empty($this->secretKey)) {
            throw new UnitpayValidationException('SecretKey is null');
        }
        $vitalParams = SignatureBuilder::stringifyFloats([
            'account'  => $account,
            'currency' => $currency,
            'desc'     => $desc,
            'sum'      => $sum,
        ]);
        $params = array_merge($this->pending->drain(), $vitalParams);
        $params['signature'] = $this->signature->build($vitalParams, $this->secretKey);
        $params['locale'] = $locale;
        $params['sdk'] = $this->getSdkToken(); // outside the signature — does not affect it
        return $this->formUrl . $publicKey . '?' . http_build_query($params);
    }

    /**
     * @template T of AbstractService
     * @param class-string<T> $class
     * @return T
     */
    private function makeService(string $class): AbstractService
    {
        return new $class(
            $this->transport,
            $this->apiUrl,
            $this->secretKey,
            $this->pending,
            self::VERSION,
            self::API_VERSION
        );
    }

    /**
     * Rejects anything that is not a bare host (optionally with a port). The domain is
     * interpolated into the API, form and IP-feed URLs, so a scheme or path would produce
     * malformed URLs.
     *
     * @throws UnitpayValidationException when $domain is not a bare host
     */
    private function assertValidDomain(string $domain): void
    {
        // Labels of 1-63 alphanumerics/hyphens (not hyphen-edged), 253 chars total, optional :port.
        $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
        $host = '(?:' . $label . '\.)*' . $label;
        if (preg_match('~^(?=.{1,253}(?::|$))' . $host . '(?::\d{1,5})?$~', $domain) !== 1) {
            throw new UnitpayValidationException(
                'Domain must be a bare host such as "unitpay.ru", without scheme, path or query, got '
                . var_export($domain, true)
            );
        }
    }

    /**
     * Machine-readable fingerprint token for the form() URL: php_<SDK version>_<PHP major.minor>.
     * major.minor so the exact PHP patch is not exposed in the buyer-visible payment form URL.
     */
    private function getSdkToken(): string
    {
        return 'php_' . self::VERSION . '_' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }
}
