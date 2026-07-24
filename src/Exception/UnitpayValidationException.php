<?php

namespace Unitpay\Exception;

/** An invalid or missing argument was passed (wrong parameter, missing key, malformed data). */
class UnitpayValidationException extends \InvalidArgumentException implements UnitpayExceptionInterface
{
}
