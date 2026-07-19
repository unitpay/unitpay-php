<?php

/**
 *  Demo handler for your projects
 *
 * @link https://help.unitpay.ru/payments/payment-handler
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

// Keep the webhook IP allowlist current WITHOUT a network call per callback:
// refresh it on a schedule (e.g. a daily cron) and cache the result —
//   $ips = (new UnitPay($domain, $secretKey))->refreshAllowedIps()->getAllowedIps();
// then feed the cached list here, plus any of your own IPs (proxy/relay):
//   $unitpay->setAllowedIps($cachedIps)->addAllowedIps(['1.2.3.4']);

try {
    // Validate request (check ip address, signature and etc)
    $unitpay->checkHandlerRequest();

    // Read the validated request from the SDK (honours an injected request, not $_GET).
    $method = $unitpay->getHandlerMethod();
    $params = $unitpay->getHandlerParams();

    // Very important! Validate request with your order data, before complete order
    if (
        $params['orderSum'] != $orderSum ||
        $params['orderCurrency'] != $orderCurrency ||
        $params['account'] != $orderId ||
        $params['projectId'] != $projectId
    ) {
        // logging data and throw exception
        throw new InvalidArgumentException('Order validation Error!');
    }

    switch ($method) {
        // Just check order (check server status, check order in DB and etc)
        case 'check':
            print $unitpay->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
            // Method Pay means that the money received
        case 'pay':
            // Please complete order
            print $unitpay->getSuccessHandlerResponse('Pay Success');
            break;
            // Method Preauth: two-stage payment — the money is only BLOCKED, not
            // captured yet. Do NOT deliver goods/services here; wait for 'pay'.
            // Just acknowledge so the notification isn't treated as failed.
        case 'preauth':
            print $unitpay->getSuccessHandlerResponse('Preauth received. Funds held, awaiting capture.');
            break;
            // Method Error means that an error has occurred.
        case 'error':
            // Please log error text.
            print $unitpay->getSuccessHandlerResponse('Error logged');
            break;
    }
    // Oops! Something went wrong.
} catch (Exception $e) {
    print $unitpay->getErrorHandlerResponse($e->getMessage());
}
