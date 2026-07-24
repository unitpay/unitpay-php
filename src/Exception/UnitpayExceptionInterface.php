<?php

namespace Unitpay\Exception;

/**
 * Marker interface implemented by every exception in this SDK, so that calling
 * code can catch any Unitpay error with a single catch. Each concrete class
 * additionally extends the SPL exception that was historically thrown, so
 * existing catch (\InvalidArgumentException | \UnexpectedValueException) blocks
 * keep working.
 */
interface UnitpayExceptionInterface
{
}
