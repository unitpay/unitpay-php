<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Read-only account-level reference calls: balance, commissions, currency rates,
 * BIN info. They authenticate with the ACCOUNT key + login — the login is a method
 * argument and the key goes in the options array, overriding the project key from the
 * constructor. getMethodsAvailable is project-level and uses the project key (no login).
 * Note that BIN lookup lives on the payouts service, next to the SBP bank list.
 * The data-changing offsetAdvance is in offsetAdvance.php.
 *
 * @link https://help.unitpay.ru/api/balance
 * @link https://help.unitpay.ru/api/commissions
 * @link https://help.unitpay.ru/api/conversion-rates
 * @link https://help.unitpay.ru/api/bin_info
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

$accountKey = ['secretKey' => $accountSecretKey];

try {
    $reference = $unitpay->reference();

    // Account balance and the amount available for withdrawal.
    var_dump($reference->getPartner($login, $accountKey)->result ?? null);

    // Acquiring commissions for the project.
    var_dump($reference->getCommissions($projectId, $login, $accountKey)->result ?? null);

    var_dump($reference->getCurrencyCourses($login, $accountKey)->result ?? null);

    // BIN — the first 6 digits of the card number.
    var_dump($unitpay->payouts()->getBinInfo($login, 424242, $accountKey)->result ?? null);

    // Payment methods available on the project: project key, no login.
    var_dump($reference->getMethodsAvailable($projectId)->result ?? null);
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
