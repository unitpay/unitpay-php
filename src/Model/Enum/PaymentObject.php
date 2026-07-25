<?php

namespace Unitpay\Model\Enum;

/**
 * Payment-object codes (признак предмета расчёта) for a 54-FZ receipt line item.
 * Const-class rather than a native enum to keep PHP 7.4 support.
 */
final class PaymentObject
{
    /** Commodity */
    public const COMMODITY = 'commodity';
    /** Job */
    public const JOB = 'job';
    /** Service */
    public const SERVICE = 'service';
    /** Lottery ticket */
    public const LOTTERY = 'lottery';
    /** Intellectual activity results */
    public const INTELLECTUAL_ACTIVITY = 'intellectual_activity';
    /** Payment (advance, deposit, prepayment, credit) */
    public const PAYMENT = 'payment';
    /** Agent commission */
    public const AGENT_COMMISSION = 'agent_commission';
    /** Contribution, penalty, fine, bonus and other similar payment object */
    public const PAYMENT_2 = 'payment_2';
    /** Other payment object */
    public const ANOTHER = 'another';
    /** Property right */
    public const PROPERTY_RIGHT = 'property_right';
    /** Non-operating gain */
    public const NON_OPERATING_GAIN = 'non-operating_gain';
    /** Insurance premiums */
    public const INSURANCE_PREMIUM = 'insurance_premium';
    /** Sales tax */
    public const SALES_TAX = 'sales_tax';
    /** Resort fee */
    public const RESORT_FEE = 'resort_fee';
    /** Deposit */
    public const DEPOSIT = 'deposit';
    /** Expense */
    public const EXPENSE = 'expense';
    /** Mandatory pension insurance contributions for a sole proprietor */
    public const PENSION_INSURANCE_IP = 'pension_insurance_ip';
    /** Mandatory pension insurance contributions */
    public const PENSION_INSURANCE = 'pension_insurance';
    /** Mandatory medical insurance contributions for a sole proprietor */
    public const MEDICAL_INSURANCE_IP = 'medical_insurance_ip';
    /** Mandatory medical insurance contributions */
    public const MEDICAL_INSURANCE = 'medical_insurance';
    /** Mandatory social insurance contributions */
    public const SOCIAL_INSURANCE = 'social_insurance';
    /** Casino payment */
    public const CASINO_PAYMENT = 'casino_payment';
    /** Cash disbursement */
    public const ISSUANCE_BANK = 'issuance_bank';
    /** Commodity subject to marking, without a mark code */
    public const COMMODITY_WITHOUT_MARK = 'commodity_without_mark';
    /** Commodity subject to marking, with a mark code */
    public const COMMODITY_MARK = 'commodity_mark';

    /** @deprecated Rejected by the public API; will be removed in 4.0. */
    public const EXCISE = 'excise';
    /** @deprecated Rejected by the public API; will be removed in 4.0. */
    public const GAMBLING_BET = 'gambling_bet';
    /** @deprecated Rejected by the public API; will be removed in 4.0. */
    public const GAMBLING_PRIZE = 'gambling_prize';
    /** @deprecated Rejected by the public API; will be removed in 4.0. */
    public const LOTTERY_PRIZE = 'lottery_prize';
    /** @deprecated Rejected by the public API; will be removed in 4.0. */
    public const COMPOSITE = 'composite';
}
