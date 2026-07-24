<?php

namespace Unitpay\Exception;

/** The method is not supported by a service call or the webhook handler. */
class UnitpayUnsupportedMethodException extends \UnexpectedValueException implements UnitpayExceptionInterface
{
}
