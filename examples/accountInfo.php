<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Read-only account-level reference calls: balance, commissions, currency rates,
 * BIN info. They authenticate with the ACCOUNT key + login, passed explicitly to
 * override the project key from the constructor. getMethodsAvailable is project-level
 * and uses the project key (no login). The data-changing offsetAdvance is in offsetAdvance.php.
 *
 * @link https://help.unitpay.ru/api/balance
 * @link https://help.unitpay.ru/api/commissions
 * @link https://help.unitpay.ru/api/conversion-rates
 * @link https://help.unitpay.ru/api/bin_info
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

try {
    // Account balance and the amount available for withdrawal.
    var_dump($unitpay->api('getPartner', $account)->result ?? null);

    // Acquiring commissions for the project.
    var_dump($unitpay->api('getCommissions', $account + ['projectId' => $projectId])->result ?? null);

    var_dump($unitpay->api('getCurrencyCourses', $account)->result ?? null);

    // BIN — the first 6 digits of the card number.
    var_dump($unitpay->api('getBinInfo', $account + ['bin' => 424242])->result ?? null);

    // Payment methods available on the project: project key, no login.
    var_dump($unitpay->api('getMethodsAvailable', ['projectId' => $projectId])->result ?? null);
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
