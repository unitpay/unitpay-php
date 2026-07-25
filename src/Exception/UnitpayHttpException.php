<?php

namespace Unitpay\Exception;

/**
 * Unitpay answered with a non-2xx status. The request reached the server and was
 * processed far enough to produce a status, so it must not be repeated blindly.
 *
 * getResponseBody() keeps whatever came back — a JSON error envelope, or the HTML error
 * page a gateway returns on a 502. That body is what an integrator quotes in a support
 * ticket, which is why it survives instead of being collapsed into the message.
 */
class UnitpayHttpException extends UnitpayTransportException
{
    public function __construct(string $message, int $statusCode, string $responseBody)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }
}
