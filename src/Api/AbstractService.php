<?php

namespace Unitpay\Api;

use Unitpay\Exception\UnitpayTransportException;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\TransportInterface;
use Unitpay\Signature\SignatureBuilder;

/**
 * Shared request pipeline for the API services: merges accumulated fluent params,
 * resolves the secret, serializes floats, attaches the telemetry fingerprint, sends
 * over the transport, and decodes the JSON envelope.
 */
abstract class AbstractService
{
    private TransportInterface $transport;
    private string $apiUrl;
    private ?string $secretKey;
    private PendingParams $pending;
    private string $sdkVersion;
    private string $apiVersion;

    public function __construct(
        TransportInterface $transport,
        string $apiUrl,
        ?string $secretKey,
        PendingParams $pending,
        string $sdkVersion,
        string $apiVersion
    ) {
        $this->transport = $transport;
        $this->apiUrl = $apiUrl;
        $this->secretKey = $secretKey;
        $this->pending = $pending;
        $this->sdkVersion = $sdkVersion;
        $this->apiVersion = $apiVersion;
    }

    /**
     * Runs a server-to-server call. Accumulated fluent params (cashItems, backUrl, ...)
     * are drained and merged, with the explicit call params taking precedence. An
     * explicit non-empty secretKey overrides the instance key so account-level methods
     * (getPartner, payouts, ...) can use the account key. The params are cleared as part
     * of drain(), symmetric with form().
     *
     * @param array<string, mixed> $params
     * @throws UnitpayValidationException when the secret key is unset/empty
     * @throws UnitpayTransportException  when no usable response comes back
     */
    protected function request(string $method, array $params): object
    {
        $params = array_merge($this->pending->drain(), $params);

        if (empty($params['secretKey'])) {
            $params['secretKey'] = $this->secretKey;
        }
        if (empty($params['secretKey'])) {
            throw new UnitpayValidationException('SecretKey is null');
        }

        $params = SignatureBuilder::stringifyFloats($params);

        $requestUrl = $this->apiUrl . '?' . http_build_query(
            ['method' => $method] + $params,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $response = json_decode($this->transport->send($requestUrl, $this->fingerprintHeaders()));
        if (!is_object($response)) {
            throw new UnitpayTransportException('Temporary server error. Please try again later.');
        }

        return $response;
    }

    /**
     * SDK self-identification headers (anonymous, no PII): a short User-Agent plus an
     * X-Unitpay-Client JSON object. api_version is the Unitpay API surface targeted;
     * platform is the coarse OS family only.
     * @return string[]
     */
    private function fingerprintHeaders(): array
    {
        $client = (string) json_encode([
            'sdk_version'  => $this->sdkVersion,
            'api_version'  => $this->apiVersion,
            'lang'         => 'php',
            'lang_version' => PHP_VERSION,
            'platform'     => PHP_OS_FAMILY,
            'publisher'    => 'unitpay',
        ]);
        return [
            'User-Agent: unitpay-php-sdk/' . $this->sdkVersion . ' api/' . $this->apiVersion,
            'X-Unitpay-Client: ' . $client,
        ];
    }
}
