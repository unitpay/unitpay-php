<?php

namespace Unitpay\Exception;

/**
 * A service call could not obtain a usable response from Unitpay.
 *
 * Base of the three concrete cases — UnitpayNetworkException (no response),
 * UnitpayHttpException (non-2xx) and UnitpayResponseException (unusable payload) — so a
 * caller that only wants "the request failed" still gets away with one catch. Every
 * accessor is nullable because each subclass fills in only what its case actually knows.
 *
 * This class deliberately takes plain scalars rather than an Http\Response: Exception is
 * a leaf layer and must not depend on Http (see .ai-factory/ARCHITECTURE.md). Turning a
 * Response into one of these lives in the Api layer.
 */
class UnitpayTransportException extends \InvalidArgumentException implements UnitpayExceptionInterface
{
    protected ?int $statusCode = null;
    protected ?int $errno = null;
    protected ?string $transportError = null;
    protected ?string $responseBody = null;

    /** HTTP status, or null when no response arrived. */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /** cURL errno (or Http\Response::ERRNO_LOCAL), or null when a response did arrive. */
    public function getErrno(): ?int
    {
        return $this->errno;
    }

    /** Transport-level error text, or null when a response did arrive. */
    public function getTransportError(): ?string
    {
        return $this->transportError;
    }

    /** Raw response body, or null when no response arrived. */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
