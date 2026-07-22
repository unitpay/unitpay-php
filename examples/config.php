<?php

/**
 * Общие настройки подключения для примеров: идентификаторы проекта/кабинета и ключи.
 * Никогда не храните секреты в коде — читайте их из окружения (или хранилища секретов).
 */

// Проект.
$domain    = 'unitpay.ru';
$projectId = 1;
$publicId  = '15155-ae12d';
$secretKey = getenv('UNITPAY_SECRET_KEY') ?: 'set-me-in-env';

// Кабинет: методы уровня кабинета (выплаты, getPartner, комиссии, курсы валют, BIN,
// offsetAdvance) аутентифицируются ключом КАБИНЕТА + login, а не ключом проекта.
// Передавайте их явно в api(), чтобы переопределить ключ проекта из конструктора.
$login            = getenv('UNITPAY_LOGIN') ?: 'partner@example.com';
$accountSecretKey = getenv('UNITPAY_ACCOUNT_SECRET_KEY') ?: 'set-account-key-in-env';
