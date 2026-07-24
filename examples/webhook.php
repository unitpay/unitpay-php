<?php

/**
 * Demo handler for your projects.
 *
 * @link https://help.unitpay.ru/payments/payment-handler
 */

use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order.php';

$unitpay = new Unitpay($domain, $secretKey);

// Inbound verification and the allowlist live on the webhook verifier.
$webhook = $unitpay->webhook();

// The handler response is JSON (getSuccessHandlerResponse/getErrorHandlerResponse).
header('Content-Type: application/json; charset=UTF-8');

// Keep the webhook IP allowlist current WITHOUT a network request on every call:
// refresh it on a schedule (e.g. a daily cron) and cache the result —
//   $ips = (new Unitpay($domain, $secretKey))->webhook()->refreshAllowedIps()->getAllowedIps();
// then pass the cached list here, plus your own IPs (proxy/relay):
//   $webhook->setAllowedIps($cachedIps)->addAllowedIps(['1.2.3.4']);

// Local debugging only: trust 127.0.0.1 to replay webhooks from this host.
// addAllowedIps() adds it ON TOP of the Unitpay list (setAllowedIps() would replace it).
// 127.0.0.1 is untrusted by default — behind a proxy on the same host REMOTE_ADDR equals
// 127.0.0.1 and would nullify the IP check — so enable this with an explicit flag and NEVER
// enable it in production.
if (getenv('UNITPAY_DEBUG_LOCAL') === '1') {
    $webhook->addAllowedIps(['127.0.0.1']);
}

try {
    // Verify the request (sender IP, signature, supported method).
    $webhook->checkHandlerRequest();

    // Read the verified request from the SDK (honors the overridden request, not $_GET).
    $method = $webhook->getHandlerMethod();
    $params = $webhook->getHandlerParams();

    // Very important: reconcile the webhook against your order data before completing the order.
    if (
        ($params['orderSum'] ?? null) != $orderSum ||
        ($params['orderCurrency'] ?? null) != $orderCurrency ||
        ($params['account'] ?? null) != $orderId ||
        ($params['projectId'] ?? null) != $projectId
    ) {
        throw new InvalidArgumentException('Order validation Error!');
    }

    switch ($method) {
        case 'check':
            // 'check' — verify the order can be paid (server status, order in the DB, ...).
            print $webhook->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
        case 'pay':
            // 'pay' — money received; complete the order here.
            print $webhook->getSuccessHandlerResponse('Pay Success');
            break;
        case 'preauth':
            // 'preauth' — two-stage payment: funds are only HELD, not yet captured.
            // Do NOT deliver goods/services here; wait for 'pay'. Acknowledge receipt so
            // the notification is not treated as failed.
            print $webhook->getSuccessHandlerResponse('Preauth received. Funds held, awaiting capture.');
            break;
        case 'error':
            // 'error' — an error occurred; log it.
            print $webhook->getSuccessHandlerResponse('Error logged');
            break;
        default:
            // Unknown method: do not leave an empty response (Unitpay would treat it as a
            // failure with no diagnostics) — return an error via the shared catch below.
            throw new InvalidArgumentException('Unexpected handler method: ' . $method);
    }
} catch (Exception $exception) {
    // Any error (wrong signature, disallowed IP, order mismatch) returns an error to Unitpay.
    print $webhook->getErrorHandlerResponse($exception->getMessage());
}
