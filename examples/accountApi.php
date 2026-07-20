<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Справочные вызовы уровня аккаунта: баланс, комиссии, курсы валют, информация по BIN,
 * чек зачёта аванса. Аутентифицируются ключом АККАУНТА + login, переданными явно, чтобы
 * переопределить ключ проекта из конструктора. getMethodsAvailable — уровня проекта и
 * использует ключ проекта (без login).
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

// Баланс аккаунта и сумма, доступная к выводу.
var_dump($unitpay->api('getPartner', $account)->result ?? null);

// Комиссии эквайринга по проекту (ключ аккаунта + projectId).
var_dump($unitpay->api('getCommissions', $account + ['projectId' => $projectId])->result ?? null);

// Курсы конвертации валют (вход / выход).
var_dump($unitpay->api('getCurrencyCourses', $account)->result ?? null);

// Информация о карте по BIN (первые 6 цифр номера карты).
var_dump($unitpay->api('getBinInfo', $account + ['bin' => 424242])->result ?? null);

// Фискальный чек зачёта аванса по ранней предоплате (создаёт чек).
var_dump($unitpay->api('offsetAdvance', $account + ['paymentId' => 3403575])->result ?? null);

// Доступные на проекте способы оплаты — ключ проекта, без login.
var_dump($unitpay->api('getMethodsAvailable', ['projectId' => $projectId])->result ?? null);
