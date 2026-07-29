<?php

namespace Tests\Support;

use Unitpay\Http\Response;
use Unitpay\Http\TransportInterface;

/**
 * Test double for the outbound HTTP transport: records every call (URL + headers) and
 * replays a queue of canned results, so the SDK can be exercised without the network.
 *
 * A queued item may be:
 *   - a string   → shorthand for HTTP 200 with that body (the common case);
 *   - false      → shorthand for a connect-phase failure (no response, request not sent);
 *   - a Response → the full result, needed for status codes, headers, and the read-timeout
 *                  case where the request DID reach the server.
 */
final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{url: string, headers: string[]}> */
    private array $calls = [];

    /** @var array<int, Response> */
    private array $responses;

    /**
     * @param string|false|Response ...$responses results returned by successive request()
     *                                            calls; the last one is reused once the
     *                                            queue runs out.
     */
    public function __construct(...$responses)
    {
        if ($responses === []) {
            $responses = ['{"result":{}}'];
        }

        /** @var array<int, string|false|Response> $responses */
        $this->responses = array_map([self::class, 'normalize'], $responses);
    }

    /**
     * @param string[] $headers
     */
    public function request(string $url, array $headers = []): Response
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers];

        $index = count($this->calls) - 1;
        $last = count($this->responses) - 1;

        return $this->responses[$index < $last ? $index : $last];
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    /** URL of the n-th call (0-based). */
    public function url(int $index = 0): string
    {
        return $this->calls[$index]['url'];
    }

    public function lastUrl(): string
    {
        return $this->url(count($this->calls) - 1);
    }

    /**
     * Query string of the n-th call, parsed into an array. parse_str() yields string
     * values plus arrays for bracketed keys, hence the union.
     *
     * @return array<int|string, array<mixed>|string>
     */
    public function query(int $index = 0): array
    {
        parse_str((string) parse_url($this->url($index), PHP_URL_QUERY), $query);

        return $query;
    }

    /** Value of a request header sent on the n-th call, or null when absent. */
    public function header(string $name, int $index = 0): ?string
    {
        foreach ($this->calls[$index]['headers'] as $header) {
            if (stripos($header, $name . ':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }

    /**
     * @param string|false|Response $response
     */
    private static function normalize($response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if ($response === false) {
            // errno 7 = CURLE_COULDNT_CONNECT: the historical meaning of a bare `false`.
            return Response::failed(7, 'Simulated transport failure', false);
        }

        return Response::received(200, $response);
    }
}
