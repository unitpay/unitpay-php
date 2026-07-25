<?php

namespace Unitpay\Http;

/**
 * The result of one outbound HTTP attempt.
 *
 * Replaces the pre-4.0 `string|false` transport return, which threw away the status
 * code, the response headers and the cURL errno — so a timeout, a 404, a 500 with an
 * HTML body, a disabled allow_url_fopen and malformed JSON all reached the caller as
 * the same "Temporary server error".
 *
 * Build one through received() or failed(): the constructor is private so a nonsensical
 * state (a "failed" result carrying HTTP 200) cannot be expressed.
 */
final class Response
{
    /**
     * Not a cURL errno. Marks a failure raised locally — a missing ext-curl combined with
     * a disabled allow_url_fopen, or a stream failure the fallback cannot classify.
     */
    public const ERRNO_LOCAL = -1;

    private int $statusCode;
    private string $body;
    /** @var string[] */
    private array $headers;
    private int $errno;
    private string $error;
    private bool $requestSent;

    /**
     * @param string[] $headers
     */
    private function __construct(
        int $statusCode,
        string $body,
        array $headers,
        int $errno,
        string $error,
        bool $requestSent
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->errno = $errno;
        $this->error = $error;
        $this->requestSent = $requestSent;
    }

    /**
     * An HTTP response came back, whatever its status. Receiving one proves the request
     * reached the server, so requestSent is always true here.
     *
     * @param string[] $headers raw response header lines, as received
     */
    public static function received(int $statusCode, string $body, array $headers = []): self
    {
        return new self($statusCode, $body, $headers, 0, '', true);
    }

    /**
     * No HTTP response came back.
     *
     * @param int  $errno       cURL errno, or ERRNO_LOCAL for a local failure
     * @param bool $requestSent whether the request had already been put on the wire — see
     *                          wasRequestSent(); pass false when it cannot be determined
     */
    public static function failed(int $errno, string $error, bool $requestSent): self
    {
        return new self(0, '', [], $errno, $error, $requestSent);
    }

    /** HTTP status, or 0 when no response arrived. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return string[] raw response header lines */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** cURL errno, ERRNO_LOCAL for a local failure, or 0 when the transport did not fail. */
    public function getErrno(): int
    {
        return $this->errno;
    }

    /** Transport-level error text, or '' when the transport did not fail. */
    public function getTransportError(): string
    {
        return $this->error;
    }

    /**
     * Whether the request was already on the wire when the attempt failed.
     *
     * This is the retry-safety signal, and the only thing a retry decision may be based
     * on. cURL reports CURLE_OPERATION_TIMEDOUT (28) for both a connect timeout and a read
     * timeout, so the errno cannot tell the two apart — but the consequence differs
     * completely: after a read timeout the server may already have created the payment,
     * and the Unitpay API accepts no idempotency key to make a repeat harmless.
     */
    public function wasRequestSent(): bool
    {
        return $this->requestSent;
    }

    /** Whether an HTTP response arrived with a 2xx status. */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
