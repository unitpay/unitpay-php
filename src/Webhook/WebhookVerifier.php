<?php

namespace Unitpay\Webhook;

use Unitpay\Exception\UnitpayIpException;
use Unitpay\Exception\UnitpaySignatureException;
use Unitpay\Exception\UnitpayUnsupportedMethodException;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\TransportInterface;
use Unitpay\Signature\SignatureBuilder;

/**
 * Verifies inbound Unitpay webhooks and builds the handler responses. A webhook is
 * trusted only when BOTH the SHA-256 signature (constant-time) and the source-IP
 * allowlist pass. Also owns the keep-fresh IP allowlist (refresh from the published
 * feed, merchant additions, effective union).
 */
class WebhookVerifier
{
    /**
     * Webhook methods Unitpay sends to the handler. 'preauth' is a notification of a
     * two-stage hold on funds (money is blocked but not yet captured): it must pass
     * verification like the others rather than be rejected as unsupported.
     */
    private const SUPPORTED_PARTNER_METHODS = ['check', 'pay', 'preauth', 'error'];

    private ?string $secretKey;
    private SignatureBuilder $signature;
    private TransportInterface $transport;
    private string $ipsUrl;
    /** @var array<string, mixed>|null */
    private ?array $request;
    private ?string $clientIp;
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
    /**
     * The merchant's own IPs, added via addAllowedIps(); always applied on top of the
     * Unitpay list and preserved across refreshAllowedIps()/setAllowedIps().
     * @var string[]
     */
    private array $customIps = [];
    private ?IpAllowlist $ipAllowlist = null;
    private ?string $handlerMethod = null;
    /** @var array<string, mixed>|null */
    private ?array $handlerParams = null;

    /**
     * @param array<string, mixed>|null $request inbound webhook array read by
     *                                   checkHandlerRequest(). Defaults to $_GET.
     * @param string|null $clientIp sender IP used by getIp(). Defaults to
     *                              $_SERVER['REMOTE_ADDR']. Override getIp() behind a proxy.
     */
    public function __construct(
        ?string $secretKey,
        SignatureBuilder $signature,
        TransportInterface $transport,
        string $ipsUrl,
        ?array $request = null,
        ?string $clientIp = null
    ) {
        $this->secretKey = $secretKey;
        $this->signature = $signature;
        $this->transport = $transport;
        $this->ipsUrl = $ipsUrl;
        $this->request = $request;
        $this->clientIp = $clientIp;
    }

    /**
     * Verifies the inbound webhook: supported method, SHA-256 signature (constant-time)
     * and the sender IP allowlist. On success it stores the verified method and params,
     * available via getHandlerMethod()/getHandlerParams().
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function checkHandlerRequest(): bool
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

        if (!in_array($method, self::SUPPORTED_PARTNER_METHODS, true)) {
            throw new UnitpayUnsupportedMethodException('Method is not supported');
        }

        if (!isset($params['signature']) || !is_string($params['signature'])
            || !hash_equals($this->signature->build($params, $this->secretKey, $method), $params['signature'])) {
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
     * The webhook method verified by the last successful checkHandlerRequest()
     * ('check' | 'pay' | 'preauth' | 'error'). null until a successful verification.
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
     * Overrides the list of Unitpay IPs allowed to call the handler. Fully replaces the
     * built-in default (or previously fetched) list, but does NOT touch the merchant IPs
     * added via addAllowedIps() — they remain on top.
     *
     * Passing an empty array with no addAllowedIps() entries leaves the allowlist empty,
     * so every webhook is rejected (fail-closed, not a no-op) — pass at least one IP/CIDR.
     *
     * @param string[] $ips exact IPs and/or CIDR ranges
     * @throws UnitpayValidationException on a malformed entry
     */
    public function setAllowedIps(array $ips): self
    {
        $this->assertValidEntries($ips);
        $this->supportedUnitpayIp = $ips;
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Adds the merchant's own IP/CIDR ranges (e.g. your proxy/relay) on top of the
     * Unitpay list. Preserved across refreshAllowedIps()/setAllowedIps(). Duplicates
     * are removed.
     *
     * @param string[] $ips exact IPs and/or CIDR ranges
     * @throws UnitpayValidationException on a malformed entry
     */
    public function addAllowedIps(array $ips): self
    {
        $this->assertValidEntries($ips);
        $this->customIps = array_values(array_unique(array_merge($this->customIps, $ips)));
        $this->ipAllowlist = null;
        return $this;
    }

    /**
     * Rejects malformed allowlist entries up front. The whole call fails, so the allowlist
     * is never left half-configured.
     *
     * The feed path does NOT go through here: refreshAllowedIps() stays fail-safe and drops
     * bad entries instead of throwing. Only the two manual setters throw.
     *
     * @param string[] $ips
     * @throws UnitpayValidationException
     */
    private function assertValidEntries(array $ips): void
    {
        foreach ($ips as $entry) {
            if (!IpAllowlist::isValidEntry($entry)) {
                throw new UnitpayValidationException(
                    'Invalid IP allowlist entry: ' . var_export($entry, true)
                    . '. Expected an IPv4/IPv6 address or a CIDR range like "77.75.153.0/25".'
                );
            }
        }
    }

    /**
     * Fetches Unitpay's current published webhook IPs and makes them the allowlist.
     *
     * Best-effort and fail-safe: on any transport/parse/validation error the previously
     * configured Unitpay list is left unchanged — the method never empties the list and
     * never throws, so it is safe to call in a chain before checkHandlerRequest(). A
     * successful fetch REPLACES the Unitpay list; merchant IPs added via addAllowedIps()
     * are preserved and always applied on top.
     *
     * Makes a blocking network request — call it periodically (e.g. a daily cron) and
     * cache getAllowedIps() yourself; do NOT call it on every webhook.
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
     * The effective allowlist actually applied by the handler: the Unitpay list plus the
     * merchant additions, de-duplicated. Cache it after refreshAllowedIps() and feed it
     * back via setAllowedIps() when handling webhooks, to avoid a network request on
     * every call.
     * @return string[]
     */
    public function getAllowedIps(): array
    {
        return array_values(array_unique(array_merge($this->supportedUnitpayIp, $this->customIps)));
    }

    /** Builds the JSON success response that Unitpay expects from the handler. */
    public function getSuccessHandlerResponse(string $message): string
    {
        return (string) json_encode(['result' => ['message' => $message]]);
    }

    /** Builds the JSON error response that Unitpay expects from the handler. */
    public function getErrorHandlerResponse(string $message): string
    {
        return (string) json_encode(['error' => ['message' => $message]]);
    }

    /**
     * Sender IP of the inbound request (the overridden clientIp or $_SERVER['REMOTE_ADDR']).
     * Override for proxy-aware logic.
     */
    protected function getIp(): string
    {
        return $this->clientIp !== null ? $this->clientIp : ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Whether $ip is allowed to call the handler. Matches exact addresses and CIDR
     * subnets (IPv4/IPv6) via IpAllowlist. Override for proxy-aware logic.
     */
    protected function isAllowedIp(string $ip): bool
    {
        if ($this->ipAllowlist === null) {
            $this->ipAllowlist = new IpAllowlist(
                array_merge($this->supportedUnitpayIp, $this->customIps)
            );
        }
        return $this->ipAllowlist->contains($ip);
    }

    /**
     * Fetches and validates the published webhook IP feed.
     *
     * Deliberately throws nothing, unlike the API services which turn the same transport
     * results into typed exceptions: a webhook handler must not start failing because a
     * best-effort feed refresh could not reach the server. Any non-2xx, any transport
     * error and any malformed payload yield null, and the caller keeps the current list.
     *
     * @return string[]|null validated non-empty list, or null on any error
     */
    private function fetchUnitpayIps(): ?array
    {
        $response = $this->transport->request($this->ipsUrl);

        return $response->isSuccessful() ? IpAllowlist::parseWebhooksFeed($response->getBody()) : null;
    }
}
