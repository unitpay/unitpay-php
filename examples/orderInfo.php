<?php

/**
 * Typical sample of order
 */

// Project Data
$domain = 'unitpay.ru';
$projectId = 1;
// Never hardcode secrets. Read them from the environment (or your config/secret store).
$secretKey = getenv('UNITPAY_SECRET_KEY') ?: 'set-me-in-env';
$publicId = '15155-ae12d';

// My item Info
$itemName = 'Iphone 6 Skin Cover';

// My Order Data
$orderId = 'a183f94-1434-1e44';
$orderSum = 900;
$orderDesc = 'Payment for item "' . $itemName . '"';
$orderCurrency  = 'RUB';

// Account-level API (payouts, getPartner, commissions, currency rates, BIN,
// offsetAdvance) authenticates with the ACCOUNT key + login (profile), not the
// project key. Pass these explicitly in api() to override the project key.
$login = getenv('UNITPAY_LOGIN') ?: 'partner@example.com';
$accountSecretKey = getenv('UNITPAY_ACCOUNT_SECRET_KEY') ?: 'set-account-key-in-env';
