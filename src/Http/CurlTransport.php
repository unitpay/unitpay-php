<?php

namespace Unitpay\Http;

use Unitpay\Exception\UnitpayValidationException;

/**
 * Default transport: cURL (with connect/read timeouts and no dependency on
 * allow_url_fopen) when ext-curl is present, otherwise a file_get_contents
 * fallback. Both paths keep TLS verification enabled.
 *
 * The file_get_contents fallback suppresses its transport warning via
 * set_error_handler (not the '@' operator, which QA rules forbid) — otherwise that
 * warning would log the URL together with the secret.
 *
 * Neither path throws: the outcome is described in a Response and classified by the
 * calling layer.
 */
final class CurlTransport implements TransportInterface
{
    private int $connectTimeout;
    private int $timeout;

    /**
     * @param int $connectTimeout seconds allowed for establishing the connection. cURL
     *                            only; the file_get_contents fallback has no separate
     *                            connect timeout and is bounded by $timeout alone.
     * @param int $timeout        seconds allowed for the whole request
     *
     * @throws UnitpayValidationException when either timeout is not positive
     */
    public function __construct(int $connectTimeout = 5, int $timeout = 10)
    {
        // Rejected here rather than at the first request, so a misconfiguration surfaces
        // where it was made. cURL reads 0 as "wait forever", which in a payment flow means
        // a request that never returns.
        self::assertPositive($connectTimeout, 'Connect timeout');
        self::assertPositive($timeout, 'Timeout');

        $this->connectTimeout = $connectTimeout;
        $this->timeout = $timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param string[] $headers
     */
    public function request(string $url, array $headers = []): Response
    {
        if (function_exists('curl_init')) {
            return $this->requestViaCurl($url, $headers);
        }

        return $this->requestViaStream($url, $headers);
    }

    /**
     * @param string[] $headers
     */
    private function requestViaCurl(string $url, array $headers): Response
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            // Prepends the header block to the body rather than taking a
            // CURLOPT_HEADERFUNCTION callback, whose mandatory handle argument would go
            // unused. Redirects are not followed, so there is exactly one block.
            CURLOPT_HEADER         => true,
        ];
        if ($headers !== []) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $connectTime = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);

        if (\PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        // curl_exec() returns true only when RETURNTRANSFER is off; it is always on here.
        if ($errno !== 0 || !is_string($raw)) {
            return Response::failed($errno, $error, self::wasRequestSent($connectTime));
        }

        return Response::received(
            $status,
            substr($raw, $headerSize),
            self::splitHeaderBlock(substr($raw, 0, $headerSize))
        );
    }

    /**
     * @param string[] $headers
     */
    private function requestViaStream(string $url, array $headers): Response
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            return Response::failed(
                Response::ERRNO_LOCAL,
                'ext-curl is not installed and allow_url_fopen is disabled in php.ini, so the SDK '
                . 'cannot make outbound requests. This is a permanent local configuration problem, '
                . 'not a temporary one: install ext-curl or enable allow_url_fopen.',
                false
            );
        }

        $http = [
            'timeout' => $this->timeout,
            // Without this a 4xx/5xx yields false and the error body is lost — exactly the
            // diagnostic this release exists to preserve.
            'ignore_errors' => true,
        ];
        if ($headers !== []) {
            $http['header'] = implode("\r\n", $headers);
        }
        $context = stream_context_create(['http' => $http]);

        set_error_handler(static function () {
            return true;
        });
        try {
            // The stream wrapper writes $http_response_header into this scope, but only
            // once a response actually arrives. Seeding it keeps the no-response case from
            // reading an undefined variable.
            $http_response_header = [];
            $body = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header;
        } finally {
            restore_error_handler();
        }

        if (!is_string($body)) {
            return Response::failed(
                Response::ERRNO_LOCAL,
                'The request failed and the file_get_contents fallback cannot report why. '
                . 'Install ext-curl for a diagnosable transport.',
                // This path exposes no connect/read phase signal, so the conservative
                // answer is the only safe one — and it is why it is never retried.
                false
            );
        }

        /** @var string[] $responseHeaders */
        return Response::received(self::parseStatus($responseHeaders), $body, $responseHeaders);
    }

    /**
     * @throws UnitpayValidationException
     */
    private static function assertPositive(int $seconds, string $label): void
    {
        if ($seconds <= 0) {
            throw new UnitpayValidationException(
                sprintf('%s must be a positive number of seconds, got %d.', $label, $seconds)
            );
        }
    }

    /**
     * Whether the request had already been handed to the wire when the attempt failed —
     * the flag the retry policy is built on, so getting it wrong risks a duplicate
     * payment rather than a duplicate log line.
     *
     * cURL reports CURLE_OPERATION_TIMEDOUT (28) for both a connect timeout and a read
     * timeout, so the errno cannot separate them. CURLINFO_CONNECT_TIME can: it is
     * exactly 0.0 until a connection is established. Measured on cURL 8.21.0:
     *
     *   case                 errno  connect_time  pretransfer_time
     *   HTTP 200                 0      0.003443          0.144292
     *   HTTP 404                 0      0.002099          0.169619
     *   DNS failure              6      0.000000          0.001893
     *   connection refused       7      0.000000          0.000316
     *   connect timeout         28      0.000000          3.003365
     *
     * Note the third column: CURLINFO_PRETRANSFER_TIME is NOT zero on connect-phase
     * failures on modern cURL, so it cannot be used for this — do not "simplify" back to
     * it.
     *
     * A TLS handshake that fails after the TCP connect reports connect_time > 0 with the
     * request still unsent, so this answers "sent" there. That is the deliberate
     * direction of the error: claiming "sent" costs a missed retry, claiming "not sent"
     * wrongly costs a second payment.
     */
    private static function wasRequestSent(float $connectTime): bool
    {
        return $connectTime > 0.0;
    }

    /**
     * Splits the raw cURL header block into the same shape the stream wrapper produces,
     * so both transports hand Response identical header lines.
     *
     * @return string[]
     */
    private static function splitHeaderBlock(string $block): array
    {
        $lines = preg_split('/\r\n|\n/', trim($block));

        return $lines === false ? [] : array_values(array_filter($lines, static function (string $line): bool {
            return $line !== '';
        }));
    }

    /**
     * Reads the status out of the response header lines. Defaults to 200 when no status
     * line is present: file_get_contents returning a string means the fetch itself
     * succeeded, and the pre-4.0 contract treated any body as a success.
     *
     * @param string[] $headers
     */
    private static function parseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 200;
    }
}
