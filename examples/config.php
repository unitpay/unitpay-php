<?php

/**
 * Shared connection settings for the examples: project/account identifiers and keys.
 * Never store secrets in code — read them from the environment (or a secrets vault).
 */

// Project.
$domain    = 'unitpay.ru';
$projectId = 1;
$publicId  = '15155-ae12d';
$secretKey = getenv('UNITPAY_SECRET_KEY') ?: 'set-me-in-env';

// Account: account-level methods (payouts, getPartner, commissions, currency rates, BIN,
// offsetAdvance) authenticate with the ACCOUNT key + login, not the project key.
// Pass them explicitly to api() to override the project key from the constructor.
$login            = getenv('UNITPAY_LOGIN') ?: 'partner@example.com';
$accountSecretKey = getenv('UNITPAY_ACCOUNT_SECRET_KEY') ?: 'set-account-key-in-env';
