<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Subscriptions: list, info, close
 *
 * @link https://help.unitpay.ru/api/subscription-list
 * @link https://help.unitpay.ru/api/subscription-info
 * @link https://help.unitpay.ru/api/close-subscription
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../UnitPay.php';

$unitpay = new UnitPay($domain, $secretKey);

$subscriptionId = 12345;

try {
    // The project's active subscriptions (add 'all' => 1 to include all statuses).
    $list = $unitpay->api('listSubscriptions', ['projectId' => $projectId]);
    var_dump($list->result ?? $list->error ?? $list);

    $info = $unitpay->api('getSubscription', ['subscriptionId' => $subscriptionId]);
    var_dump($info->result ?? $info->error ?? $info);

    // Close it (stops charges, detaches the card — irreversible).
    $closed = $unitpay->api('closeSubscription', ['subscriptionId' => $subscriptionId]);
    if (isset($closed->result->message)) {
        print $closed->result->message;
    } elseif (isset($closed->error->message)) {
        print 'Error: ' . $closed->error->message;
    }
} catch (UnitpayExceptionInterface $e) {
    print 'SDK error: ' . $e->getMessage();
}
