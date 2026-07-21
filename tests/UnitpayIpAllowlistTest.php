<?php

namespace Tests;

use UnitpayIpAllowlist;
use PHPUnit\Framework\TestCase;

/**
 * Прямые тесты сопоставления IP с белым списком. contains() критичен для безопасности, но до
 * сих пор проверялся только косвенно через checkHandlerRequest(); здесь фиксируем
 * граничные случаи (несовпадение семейств адресов, чрезмерная длина префикса,
 * некорректный клиентский IP), которые трудно выразить через полный путь обработчика.
 */
final class UnitpayIpAllowlistTest extends TestCase
{
    private function matcher(): UnitpayIpAllowlist
    {
        return new UnitpayIpAllowlist(['31.186.100.49', '203.0.113.0/24', '2001:db8::/32']);
    }

    public function testExactIpv4AddressMatches()
    {
        $this->assertTrue($this->matcher()->contains('31.186.100.49'));
    }

    public function testUnlistedIpv4AddressDoesNotMatch()
    {
        $this->assertFalse($this->matcher()->contains('8.8.8.8'));
    }

    public function testAddressInsideIpv4CidrMatches()
    {
        $this->assertTrue($this->matcher()->contains('203.0.113.55'));
    }

    public function testAddressOutsideIpv4CidrDoesNotMatch()
    {
        $this->assertFalse($this->matcher()->contains('203.0.114.1'));
    }

    public function testAddressInsideIpv6CidrMatches()
    {
        $this->assertTrue($this->matcher()->contains('2001:db8::1'));
    }

    /**
     * Точная запись IPv6 матчится вне зависимости от текстовой формы (регистр, сжатие):
     * сравнение идёт по упакованному in_addr, а не по строке.
     */
    public function testExactIpv6MatchesRegardlessOfTextualForm()
    {
        $upper = new UnitpayIpAllowlist(['2001:DB8::1']);
        $this->assertTrue($upper->contains('2001:db8::1'));

        $expanded = new UnitpayIpAllowlist(['2001:db8:0:0:0:0:0:1']);
        $this->assertTrue($expanded->contains('2001:db8::1'));
    }

    /** Некорректный клиентский IP не должен приводить к ложному совпадению. */
    public function testInvalidClientIpDoesNotMatch()
    {
        $this->assertFalse($this->matcher()->contains('not-an-ip'));
    }

    /**
     * IPv4-клиент против исключительно IPv6-подсети: inet_pton даёт in_addr разной
     * длины, поэтому сравнение должно аккуратно провалиться, а не сматчиться по ошибке.
     */
    public function testIpv4ClientAgainstIpv6OnlySubnetDoesNotMatch()
    {
        $matcher = new UnitpayIpAllowlist(['2001:db8::/32']);

        $this->assertFalse($matcher->contains('203.0.113.55'));
    }

    /** Префикс длиннее самого адреса (/33 для IPv4) не может сматчить ничего. */
    public function testPrefixWiderThanAddressDoesNotMatch()
    {
        $matcher = new UnitpayIpAllowlist(['203.0.113.0/33']);

        $this->assertFalse($matcher->contains('203.0.113.5'));
    }

    /** Граница подсети /25: адрес выше верхней границы диапазона не попадает. */
    public function testCidrBoundaryIsRespected()
    {
        $matcher = new UnitpayIpAllowlist(['77.75.153.0/25']);

        $this->assertTrue($matcher->contains('77.75.153.127'));
        $this->assertFalse($matcher->contains('77.75.153.128'));
    }

    // --- parseWebhooksFeed() ---------------------------------------------------

    public function testParseWebhooksFeedReturnsDedupedList()
    {
        $body = json_encode(['webhooks' => ['1.1.1.1', '1.1.1.1', '2.2.2.2']]);

        $this->assertSame(['1.1.1.1', '2.2.2.2'], UnitpayIpAllowlist::parseWebhooksFeed($body));
    }

    public function testParseWebhooksFeedKeepsOnlyValidEntries()
    {
        $body = json_encode(['webhooks' => ['203.0.113.0/24', 'garbage', '2001:db8::1']]);

        $this->assertSame(['203.0.113.0/24', '2001:db8::1'], UnitpayIpAllowlist::parseWebhooksFeed($body));
    }

    /**
     * @dataProvider unusableFeeds
     */
    public function testParseWebhooksFeedReturnsNullForUnusableInput(string $body)
    {
        $this->assertNull(UnitpayIpAllowlist::parseWebhooksFeed($body));
    }

    public function unusableFeeds(): array
    {
        return [
            'empty string'        => [''],
            'malformed json'      => ['this is not json'],
            'missing webhooks'    => ['{"foo":1}'],
            'webhooks not array'  => ['{"webhooks":42}'],
            'only invalid entries' => ['{"webhooks":["garbage","999.999.999.999"]}'],
        ];
    }
}
