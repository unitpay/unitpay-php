<?php

namespace Unitpay\Exception;

/**
 * The webhook signature is valid, but the payment timestamp it carries is outside the
 * tolerance window — or is not a timestamp the SDK can read.
 *
 * Extends UnitpaySignatureException rather than standing alone: this is a freshness
 * failure of the signed payload, and a handler that already rejects a webhook on
 * UnitpaySignatureException does the right thing here without being changed.
 */
class UnitpayReplayException extends UnitpaySignatureException
{
}
