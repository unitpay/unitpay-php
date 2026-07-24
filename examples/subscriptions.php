<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Subscriptions: list, info, close
 *
 * @link https://help.unitpay.ru/api/subscription-list
 * @link https://help.unitpay.ru/api/subscription-info
 * @link https://help.unitpay.ru/api/close-subscription
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

$subscriptionId = 12345;

try {
    $subscriptions = $unitpay->subscriptions();

    // The project's active subscriptions (pass ['all' => 1] to include all statuses).
    $list = $subscriptions->listSubscriptions($projectId);
    var_dump($list->result ?? $list->error ?? $list);

    $info = $subscriptions->getSubscription($subscriptionId);
    var_dump($info->result ?? $info->error ?? $info);

    // Close it (stops charges, detaches the card — irreversible).
    $closed = $subscriptions->closeSubscription($subscriptionId);
    if (isset($closed->result->message)) {
        print $closed->result->message;
    } elseif (isset($closed->error->message)) {
        print 'Error: ' . $closed->error->message;
    }
} catch (UnitpayExceptionInterface $exception) {
    print 'SDK error: ' . $exception->getMessage();
}
