<?php

namespace Unitpay\Http;

/**
 * Default transport: cURL (with connect/read timeouts and no dependency on
 * allow_url_fopen) when ext-curl is present, otherwise a file_get_contents
 * fallback. Both paths keep TLS verification enabled.
 *
 * The file_get_contents fallback suppresses its transport warning via
 * set_error_handler (not the '@' operator, which QA rules forbid) — otherwise that
 * warning would log the URL together with the secret.
 */
final class CurlTransport implements TransportInterface
{
    /**
     * @param string[] $headers
     * @return string|false
     */
    public function send(string $url, array $headers = [])
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
            ];
            if ($headers !== []) {
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            if (\PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            // curl_exec() returns true only when RETURNTRANSFER is off; it is always on here.
            return is_string($body) ? $body : false;
        }

        $http = ['timeout' => 10];
        if ($headers !== []) {
            $http['header'] = implode("\r\n", $headers);
        }
        $context = stream_context_create(['http' => $http]);
        set_error_handler(static function () {
            return true;
        });
        try {
            return file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
    }
}
