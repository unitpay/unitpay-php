<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Справочные вызовы уровня кабинета (только для чтения): баланс, комиссии, курсы валют,
 * информация по BIN. Аутентифицируются ключом КАБИНЕТА + login, переданными явно, чтобы
 * переопределить ключ проекта из конструктора. getMethodsAvailable — уровня проекта и
 * использует ключ проекта (без login). Изменяющий данные offsetAdvance — в offsetAdvance.php.
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
    // Баланс кабинета и сумма, доступная к выводу.
    var_dump($unitpay->api('getPartner', $account)->result ?? null);

    // Комиссии эквайринга по проекту.
    var_dump($unitpay->api('getCommissions', $account + ['projectId' => $projectId])->result ?? null);

    var_dump($unitpay->api('getCurrencyCourses', $account)->result ?? null);

    // BIN — первые 6 цифр номера карты.
    var_dump($unitpay->api('getBinInfo', $account + ['bin' => 424242])->result ?? null);

    // Способы оплаты на проекте: ключ проекта, без login.
    var_dump($unitpay->api('getMethodsAvailable', ['projectId' => $projectId])->result ?? null);
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
