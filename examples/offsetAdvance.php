<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Чек зачёта аванса (offsetAdvance): по ранней предоплате создаёт фискальный чек.
 * ВНИМАНИЕ: вызов создаёт чек, это не справочный метод только для чтения. API уровня кабинета:
 * аутентифицируется ключом КАБИНЕТА + login, переданными явно.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

$account = ['login' => $login, 'secretKey' => $accountSecretKey];

try {
    $response = $unitpay->api('offsetAdvance', $account + ['paymentId' => 3403575]);
    var_dump($response->result ?? $response->error ?? $response);
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
