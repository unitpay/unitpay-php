<?php

namespace Unitpay\Webhook;

use DateTimeImmutable;
use DateTimeZone;
use Unitpay\Exception\UnitpayIpException;
use Unitpay\Exception\UnitpayReplayException;
use Unitpay\Exception\UnitpaySignatureException;
use Unitpay\Exception\UnitpayUnsupportedMethodException;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\TransportInterface;
use Unitpay\Signature\SignatureBuilder;

/**
 * Verifies inbound Unitpay webhooks and builds the handler responses. A webhook is
 * trusted only when ALL of the SHA-256 signature (constant-time), the freshness of its
 * `date` and the source-IP allowlist pass, checked in that order. Also owns the
 * keep-fresh IP allowlist (refresh from the published feed, merchant additions,
 * effective union).
 *
 * The freshness check is what stops a captured webhook from being replayed indefinitely:
 * a signature proves a request was genuine once, not that it is genuine now. It is on by
 * default with a 300s window — see setWebhookTolerance().
 */
class WebhookVerifier
{
    /**
     * Webhook methods Unitpay sends to the handler. 'preauth' is a notification of a
     * two-stage hold on funds (money is blocked but not yet captured): it must pass
     * verification like the others rather than be rejected as unsupported.
     */
    private const SUPPORTED_PARTNER_METHODS = ['check', 'pay', 'preauth', 'error'];

    /**
     * Timezone of the `date` param. Unitpay documents the format (`Y-m-d H:i:s`) but not
     * the zone; it is Moscow time, fixed offset. Parsing against it explicitly — rather
     * than with strtotime(), which honours the ambient date.timezone — is what keeps the
     * verdict identical on a UTC server and a Moscow one.
     */
    private const WEBHOOK_TIMEZONE = '+03:00';

    /** Default replay window, in seconds. Matches what Stripe uses. */
    private const DEFAULT_TOLERANCE = 300;

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
    private int $tolerance = self::DEFAULT_TOLERANCE;
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

        // After the signature, never before it: an unsigned payload must not be usable to
        // probe how far this server's clock is off.
        $this->assertFresh($params);

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
     * Sets how far the webhook's `date` may be from this server's clock, in seconds.
     * Defaults to 300. Pass 0 to disable the check — a webhook then stays replayable for
     * as long as its signature is valid, which is forever.
     *
     * Raise it if your handler sits behind a queue that can hold a webhook for minutes,
     * or if the server clock is not synchronised. Both are better fixed than tolerated.
     *
     * @throws UnitpayValidationException on a negative value
     */
    public function setWebhookTolerance(int $seconds): self
    {
        if ($seconds < 0) {
            throw new UnitpayValidationException(
                sprintf('Webhook tolerance must not be negative, got %d.', $seconds)
            );
        }
        $this->tolerance = $seconds;

        return $this;
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
     * Current unix time. A seam so tests can move the clock without sleeping — the same
     * role getIp() and isAllowedIp() play for the request.
     */
    protected function now(): int
    {
        return time();
    }

    /**
     * Rejects a webhook whose payment timestamp is too far from this server's clock.
     *
     * Safe to trust because `date` is part of the signed payload — SignatureBuilder hashes
     * every param except the signature itself — so it cannot be back-dated, and it cannot
     * be stripped either: removing it changes the hash and the signature check above fails
     * first. That last point is why an absent `date` is allowed through rather than
     * refused. It is documented for all four inbound methods, but if Unitpay ever omits it
     * the absence is theirs, not an attacker's, and rejecting those webhooks would cost
     * the merchant real notifications while preventing nothing.
     *
     * @param array<string, mixed> $params
     * @throws UnitpayReplayException when the timestamp is unreadable or outside the window
     */
    private function assertFresh(array $params): void
    {
        if ($this->tolerance === 0 || !isset($params['date'])) {
            return;
        }

        $timestamp = self::parseWebhookDate($params['date']);
        if ($timestamp === null) {
            throw new UnitpayReplayException(sprintf(
                'Webhook timestamp "%s" is not a valid Y-m-d H:i:s date.',
                is_scalar($params['date']) ? (string) $params['date'] : gettype($params['date'])
            ));
        }

        $drift = abs($this->now() - $timestamp);
        if ($drift > $this->tolerance) {
            throw new UnitpayReplayException(sprintf(
                'Webhook timestamp %s is outside the %ds tolerance window (off by %ds).',
                (string) $params['date'],
                $this->tolerance,
                $drift
            ));
        }
    }

    /**
     * @param mixed $value
     * @return int|null unix timestamp, or null when the value is not a well-formed date
     */
    private static function parseWebhookDate($value): ?int
    {
        if (!is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $value,
            new DateTimeZone(self::WEBHOOK_TIMEZONE)
        );
        if ($date === false) {
            return null;
        }

        // createFromFormat accepts overflowing components ("2026-13-45") and reports them
        // as warnings instead of failing, so the strict answer needs this second look.
        // PHP >= 8.2 returns false here when there is nothing to report.
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->getTimestamp();
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
