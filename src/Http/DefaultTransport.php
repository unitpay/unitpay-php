<?php

namespace Unitpay\Http;

/**
 * Builds the transport stack the facade uses when the caller injects none: cURL (with a
 * file_get_contents fallback) behind the retry policy.
 *
 * Kept out of the facade so the composition root does not have to know how the default
 * stack is assembled, and so documentation has one name to point at when explaining what
 * "the default transport" actually is.
 */
final class DefaultTransport
{
    public static function create(): TransportInterface
    {
        return new RetryingTransport(new CurlTransport());
    }

    /**
     * The same stack with retries removed — cURL alone. Use it when the caller would
     * rather see a connect failure immediately than have the SDK spend a few seconds
     * repeating a request that never left the client.
     */
    public static function withoutRetries(): TransportInterface
    {
        return new CurlTransport();
    }
}
