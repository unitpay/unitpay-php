<?php

namespace Unitpay\Exception;

/** The webhook arrived from an IP address outside the allowlist. */
class UnitpayIpException extends \InvalidArgumentException implements UnitpayExceptionInterface
{
}
