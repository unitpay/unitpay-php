<?php

namespace Unitpay\Exception;

/**
 * No HTTP response arrived at all: DNS failure, refused connection, a timeout, or a
 * local configuration problem such as a missing ext-curl with allow_url_fopen disabled.
 *
 * Read getMessage() before retrying. A connect-phase failure never reached Unitpay and
 * is safe to repeat; a read timeout did reach it, so a repeated initPayment can create a
 * second payment — the Unitpay API accepts no idempotency key to make that harmless.
 */
class UnitpayNetworkException extends UnitpayTransportException
{
    public function __construct(string $message, int $errno, string $transportError)
    {
        parent::__construct($message);
        $this->errno = $errno;
        $this->transportError = $transportError;
    }
}
