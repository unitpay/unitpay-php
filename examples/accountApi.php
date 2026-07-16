<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Account-level reference calls: balance, commissions, currency rates, BIN info,
 * advance-offset receipt. They authenticate with the ACCOUNT key + login, passed
 * explicitly to override the project key from the constructor.
 * getMethodsAvailable is project-level and uses the project key (no login).
 *
 * @link https://help.unitpay.ru/api/balance
 * @link https://help.unitpay.ru/api/commissions
 * @link https://help.unitpay.ru/api/conversion-rates
 * @link https://help.unitpay.ru/api/bin_info
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

// Account balance and amount available for payout:
var_dump($unitpay->api('getPartner', $account)->result ?? null);

// Acquiring commissions for a project (account key + projectId):
var_dump($unitpay->api('getCommissions', $account + ['projectId' => $projectId])->result ?? null);

// Currency conversion rates (in / out):
var_dump($unitpay->api('getCurrencyCourses', $account)->result ?? null);

// Card info by BIN (first 6 digits of the card number):
var_dump($unitpay->api('getBinInfo', $account + ['bin' => 424242])->result ?? null);

// Advance-offset fiscal receipt for an earlier prepayment (creates a receipt):
var_dump($unitpay->api('offsetAdvance', $account + ['paymentId' => 3403575])->result ?? null);

// Payment methods available on the project — project key, no login:
var_dump($unitpay->api('getMethodsAvailable', ['projectId' => $projectId])->result ?? null);
