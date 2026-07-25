<?php

namespace Unitpay\Model\Enum;

/**
 * Payment-method codes for the `paymentType` param of PaymentService::initPayment()
 * and PayoutService::massPayment(). The source of truth is the backend:
 * https://help.unitpay.ru/book-of-reference/payment-system-codes
 * `paymentType` is NOT validated against these values — the constants only guard
 * against typos and provide autocompletion, so a new backend code does not require
 * an SDK release.
 */
final class PaymentType
{
    /** Bank cards (worldwide card acceptance) */
    public const CARD = 'card';
    /** Foreign cards via the acquiring bank's form */
    public const CARD_INVOICE = 'cardInvoice';
    /** Faster Payments System (SBP) */
    public const SBP = 'sbp';
    /** SberPay */
    public const SBERPAY = 'sberpay';
    /** Tinkoff Pay */
    public const TINKOFFPAY = 'tinkoffpay';
    /** PayPal */
    public const PAYPAL = 'paypal';
    /** WebMoney (WMZ wallets) */
    public const WEBMONEY = 'webmoney';
}
