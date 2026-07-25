<?php

namespace Unitpay\Http;

/**
 * Outbound HTTP transport used by the API services and the webhook IP-feed fetch.
 * Implementations perform an HTTP GET and describe the outcome in a Response — they
 * report failures through it rather than throwing, so the calling layer owns the
 * decision of which exception a given outcome deserves. Inject a fake in tests to
 * exercise the SDK without the network.
 */
interface TransportInterface
{
    /**
     * @param string[] $headers HTTP headers of the form "Name: value"
     */
    public function request(string $url, array $headers = []): Response;
}
