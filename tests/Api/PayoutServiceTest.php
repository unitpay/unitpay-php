<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransport;
use Unitpay\Unitpay;

/**
 * Mass-payout surface. Every method is account-level: it carries the account email as
 * `login` and accepts an account `secretKey` override. Successor of the pre-3.0 test
 * that asserted the payout methods were in the api() allowlist and required `login`.
 */
final class PayoutServiceTest extends TestCase
{
    private function unitpay(FakeTransport $transport): Unitpay
    {
        return new Unitpay('unitpay.test', 'secret', $transport);
    }

    public function testMassPaymentSendsItsRequiredParams(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPayment(
            'partner@example.com',
            1782,
            10,
            '79510000071',
            'sbp',
            ['memberId' => '100000000111']
        );

        $query = $transport->query();
        $this->assertSame('massPayment', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
        $this->assertSame('1782', $query['transactionId']);
        $this->assertSame('10', $query['sum']);
        $this->assertSame('79510000071', $query['purse']);
        $this->assertSame('sbp', $query['paymentType']);
        $this->assertSame('100000000111', $query['memberId']);
    }

    public function testPayoutRequestUrlUsesFlatParams(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPayment('partner@example.com', 1782, 10, '79510000071', 'sbp');

        $url = $transport->lastUrl();
        $this->assertStringContainsString('method=massPayment', $url);
        $this->assertStringContainsString('transactionId=1782', $url);
        $this->assertStringContainsString('purse=79510000071', $url);
        $this->assertStringNotContainsString('params%5B', $url);
    }

    public function testMassPaymentStatusSendsLoginAndTransactionId(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPaymentStatus('partner@example.com', 1782);

        $query = $transport->query();
        $this->assertSame('massPaymentStatus', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
        $this->assertSame('1782', $query['transactionId']);
    }

    public function testMassPaymentAvailableAmountSendsItsRequiredParams(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPaymentAvailableAmount(
            'partner@example.com',
            10,
            '79510000071',
            'sbp'
        );

        $query = $transport->query();
        $this->assertSame('massPaymentAvailableAmount', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
        $this->assertSame('10', $query['sum']);
        $this->assertSame('79510000071', $query['purse']);
        $this->assertSame('sbp', $query['paymentType']);
    }

    public function testMassPaymentCommissionsSendsLogin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPaymentCommissions('partner@example.com');

        $query = $transport->query();
        $this->assertSame('massPaymentCommissions', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
    }

    public function testGetSbpBankListSendsLogin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->getSbpBankList('partner@example.com');

        $query = $transport->query();
        $this->assertSame('getSbpBankList', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
    }

    public function testGetBinInfoSendsLoginAndBin(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->getBinInfo('partner@example.com', '220220');

        $query = $transport->query();
        $this->assertSame('getBinInfo', $query['method']);
        $this->assertSame('partner@example.com', $query['login']);
        $this->assertSame('220220', $query['bin']);
    }

    /** Payouts run on the account key, which overrides the project key from the constructor. */
    public function testAccountSecretKeyOverridesProjectKey(): void
    {
        $transport = new FakeTransport();

        $this->unitpay($transport)->payouts()->massPaymentCommissions('partner@example.com', [
            'secretKey' => 'account-key',
        ]);

        $this->assertSame('account-key', $transport->query()['secretKey']);
    }
}
