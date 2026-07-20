<?php

/**
 * Типовой пример данных заказа
 */

$domain = 'unitpay.ru';
$projectId = 1;
// Никогда не храните секреты в коде — читайте их из окружения (или вашего хранилища секретов).
$secretKey = getenv('UNITPAY_SECRET_KEY') ?: 'set-me-in-env';
$publicId = '15155-ae12d';

$itemName = 'Iphone 6 Skin Cover';

$orderId = 'a183f94-1434-1e44';
$orderSum = 900;
$orderDesc = 'Payment for item "' . $itemName . '"';
$orderCurrency  = 'RUB';

// API уровня аккаунта (выплаты, getPartner, комиссии, курсы валют, BIN,
// offsetAdvance) аутентифицируется ключом АККАУНТА + login (профиль), а не ключом
// проекта. Передавайте их явно в api(), чтобы переопределить ключ проекта.
$login = getenv('UNITPAY_LOGIN') ?: 'partner@example.com';
$accountSecretKey = getenv('UNITPAY_ACCOUNT_SECRET_KEY') ?: 'set-account-key-in-env';
