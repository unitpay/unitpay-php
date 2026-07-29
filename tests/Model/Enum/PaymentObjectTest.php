<?php

namespace Tests\Model\Enum;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Unitpay\Model\Enum\PaymentObject;

/**
 * The PaymentObject constants must stay in sync with the payment-object codes (признак
 * предмета расчёта) Unitpay accepts in a cashItems line item:
 * https://help.unitpay.ru/payments/create-payment
 *
 * Five codes the public API rejects — excise, gambling_bet, gambling_prize, lottery_prize
 * and composite — were announced for removal in 2.1.0 and removed in 4.0.0. They are
 * deliberately absent, and the first case below is what keeps them absent: a merge or a
 * well-meant "restore the full dictionary" edit that brings any of them back fails here.
 *
 * Both cases go through reflection rather than naming the constants: referencing
 * PaymentObject::EXCISE would be a PHPStan failure and then a fatal Error at runtime,
 * so an absence cannot be asserted directly.
 */
final class PaymentObjectTest extends TestCase
{
    /** Codes removed in 4.0.0. Nothing may reintroduce them. */
    private const REMOVED_IN_4_0 = [
        'EXCISE',
        'GAMBLING_BET',
        'GAMBLING_PRIZE',
        'LOTTERY_PRIZE',
        'COMPOSITE',
    ];

    public function testRemovedValuesAreGone(): void
    {
        $constants = $this->constants();

        foreach (self::REMOVED_IN_4_0 as $name) {
            $this->assertArrayNotHasKey(
                $name,
                $constants,
                sprintf(
                    'PaymentObject::%s was removed in 4.0.0 because the public API rejects it. '
                    . 'Re-adding it is a regression, not a restoration.',
                    $name
                )
            );
        }
    }

    /**
     * Pins the surviving dictionary whole: a value typo introduced while editing neighbouring
     * lines fails here, and so does an undocumented backend addition — which CLAUDE.md requires
     * to be recorded in CHANGELOG.md before it lands.
     */
    public function testSupportedValuesMatchTheBackendDictionary(): void
    {
        $expected = [
            'COMMODITY' => 'commodity',
            'JOB' => 'job',
            'SERVICE' => 'service',
            'LOTTERY' => 'lottery',
            'INTELLECTUAL_ACTIVITY' => 'intellectual_activity',
            'PAYMENT' => 'payment',
            'AGENT_COMMISSION' => 'agent_commission',
            'PAYMENT_2' => 'payment_2',
            'ANOTHER' => 'another',
            'PROPERTY_RIGHT' => 'property_right',
            'NON_OPERATING_GAIN' => 'non-operating_gain',
            'INSURANCE_PREMIUM' => 'insurance_premium',
            'SALES_TAX' => 'sales_tax',
            'RESORT_FEE' => 'resort_fee',
            'DEPOSIT' => 'deposit',
            'EXPENSE' => 'expense',
            'PENSION_INSURANCE_IP' => 'pension_insurance_ip',
            'PENSION_INSURANCE' => 'pension_insurance',
            'MEDICAL_INSURANCE_IP' => 'medical_insurance_ip',
            'MEDICAL_INSURANCE' => 'medical_insurance',
            'SOCIAL_INSURANCE' => 'social_insurance',
            'CASINO_PAYMENT' => 'casino_payment',
            'ISSUANCE_BANK' => 'issuance_bank',
            'COMMODITY_WITHOUT_MARK' => 'commodity_without_mark',
            'COMMODITY_MARK' => 'commodity_mark',
        ];
        $actual = $this->constants();

        // Sorted on both sides so declaration order stays free to change; content does not.
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, string>
     */
    private function constants(): array
    {
        /** @var array<string, string> $constants */
        $constants = (new ReflectionClass(PaymentObject::class))->getConstants();

        return $constants;
    }
}
