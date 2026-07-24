<?php

namespace Unitpay\Exception;

/** api() could not obtain a usable response from Unitpay (network or response parsing). */
class UnitpayTransportException extends \InvalidArgumentException implements UnitpayExceptionInterface
{
}
