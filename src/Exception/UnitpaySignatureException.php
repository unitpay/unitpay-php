<?php

namespace Unitpay\Exception;

/** The webhook signature did not match (possible forgery). */
class UnitpaySignatureException extends \InvalidArgumentException implements UnitpayExceptionInterface
{
}
