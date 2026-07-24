<?php

namespace Unitpay\Http;

/**
 * Outbound HTTP transport used by the API services and the webhook IP-feed fetch.
 * Implementations perform an HTTP GET and return the raw response body, or false
 * on a transport error. Inject a fake in tests to exercise the SDK without the
 * network.
 */
interface TransportInterface
{
    /**
     * @param string[] $headers HTTP headers of the form "Name: value"
     * @return string|false raw response body, or false on a transport error
     */
    public function send(string $url, array $headers = []);
}
