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
// offsetAdvance) authenticate with the ACCOUNT key + login, not the project key. The login
// is the method's first argument; the key goes in the options array as 'secretKey' and
// overrides the project key from the constructor.
$login            = getenv('UNITPAY_LOGIN') ?: 'partner@example.com';
$accountSecretKey = getenv('UNITPAY_ACCOUNT_SECRET_KEY') ?: 'set-account-key-in-env';

// Integration identity: setModule() names what you wrote, setStack() what it runs on. These
// ride along in User-Agent / Unitpay-Client on every service call and are product names and
// versions only — no PII. Skip them for a bare script; fill them in if you ship a module.
// $unitpay->setModule('unitpay-bitrix', '3.1')->setStack(['Bitrix' => '22.0']);
// Opt out entirely with $unitpay->disableTelemetry().
