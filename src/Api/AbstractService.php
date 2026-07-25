<?php

namespace Unitpay\Api;

use Unitpay\Exception\UnitpayHttpException;
use Unitpay\Exception\UnitpayNetworkException;
use Unitpay\Exception\UnitpayResponseException;
use Unitpay\Exception\UnitpayTransportException;
use Unitpay\Exception\UnitpayValidationException;
use Unitpay\Http\Response;
use Unitpay\Http\TransportInterface;
use Unitpay\Signature\SignatureBuilder;
use Unitpay\Telemetry\ClientInfo;

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
    private ClientInfo $clientInfo;

    public function __construct(
        TransportInterface $transport,
        string $apiUrl,
        ?string $secretKey,
        PendingParams $pending,
        ClientInfo $clientInfo
    ) {
        $this->transport = $transport;
        $this->apiUrl = $apiUrl;
        $this->secretKey = $secretKey;
        $this->pending = $pending;
        $this->clientInfo = $clientInfo;
    }

    /**
     * Merges the accumulated fluent params (cashItems, backUrl, customerEmail,
     * customerPhone) into a call's own params and clears them; explicit call params take
     * precedence. Only initPayment() and offsetAdvance() accept them.
     *
     * Clearing happens before the request, so a failed call consumes them too and a retry
     * must re-apply the setters — symmetric with form().
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function withPending(array $params): array
    {
        return array_merge($this->pending->drain(), $params);
    }

    /**
     * Runs a server-to-server call. An explicit non-empty secretKey overrides the instance
     * key so account-level methods (getPartner, payouts, ...) can use the account key.
     *
     * This does NOT touch the accumulated fluent params: a consuming method opts in
     * explicitly by wrapping its params in withPending().
     *
     * @param array<string, mixed> $params
     * @throws UnitpayValidationException when the secret key is unset/empty
     * @throws UnitpayTransportException  when no usable response comes back
     */
    protected function request(string $method, array $params): object
    {
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

        return $this->decode($this->transport->request($requestUrl, $this->clientInfo->headers()));
    }

    /**
     * Turns a transport result into the decoded JSON envelope, or into the exception that
     * describes what actually went wrong. Before 4.0 every branch below collapsed into one
     * "Temporary server error" — including a disabled allow_url_fopen, which is permanent.
     *
     * @throws UnitpayNetworkException  no response arrived
     * @throws UnitpayHttpException     a response arrived with a non-2xx status
     * @throws UnitpayResponseException a 2xx arrived whose body is not a JSON object
     */
    private function decode(Response $response): object
    {
        if ($response->getStatusCode() === 0) {
            throw new UnitpayNetworkException(
                $this->networkMessage($response),
                $response->getErrno(),
                $response->getTransportError()
            );
        }

        if (!$response->isSuccessful()) {
            throw new UnitpayHttpException(
                sprintf('Unitpay API returned HTTP %d.', $response->getStatusCode()),
                $response->getStatusCode(),
                $response->getBody()
            );
        }

        $decoded = json_decode($response->getBody());
        if (!is_object($decoded)) {
            throw new UnitpayResponseException(
                sprintf(
                    'Unitpay API returned HTTP %d with a body that is not a JSON object (%s).',
                    $response->getStatusCode(),
                    json_last_error_msg()
                ),
                $response->getStatusCode(),
                $response->getBody()
            );
        }

        return $decoded;
    }

    /**
     * Whether the request reached Unitpay decides what the caller may safely do next, so
     * the message says it outright. The API accepts no idempotency key, so a blind repeat
     * of a delivered initPayment can create a second payment.
     */
    private function networkMessage(Response $response): string
    {
        $detail = sprintf('%s (error %d)', $response->getTransportError(), $response->getErrno());

        if ($response->wasRequestSent()) {
            return 'No response from the Unitpay API: ' . $detail
                . '. The request was sent, so it may already have been processed — check the'
                . ' payment state before repeating it.';
        }

        return 'Could not reach the Unitpay API: ' . $detail
            . '. The request was not sent, so nothing was processed.';
    }

}
