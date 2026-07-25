<?php

namespace Unitpay\Exception;

/**
 * Unitpay answered with a 2xx, but the body is not the JSON object the API promises —
 * an empty body, HTML from an intercepting proxy, or truncated JSON.
 *
 * A protocol problem, not a network one: the call was delivered and accepted, so
 * repeating it carries the same risk as repeating any successful request.
 */
class UnitpayResponseException extends UnitpayTransportException
{
    public function __construct(string $message, int $statusCode, string $responseBody)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }
}
