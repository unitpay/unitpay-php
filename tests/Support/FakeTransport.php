<?php

namespace Tests\Support;

use Unitpay\Http\TransportInterface;

/**
 * Test double for the outbound HTTP transport: records every call (URL + headers) and
 * replays a queue of canned bodies, so the SDK can be exercised without the network.
 * Replaces the constructor callable the pre-3.0 suite injected.
 */
final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{url: string, headers: string[]}> */
    private array $calls = [];

    /** @var array<int, string|false> */
    private array $responses;

    /**
     * @param string|false ...$responses bodies returned by successive send() calls; the
     *                                   last one is reused once the queue runs out. Pass
     *                                   false to simulate a transport failure.
     */
    public function __construct(...$responses)
    {
        /** @var array<int, string|false> $responses */
        $this->responses = $responses === [] ? ['{"result":{}}'] : $responses;
    }

    /**
     * @param string[] $headers
     * @return string|false
     */
    public function send(string $url, array $headers = [])
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
}
