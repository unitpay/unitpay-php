<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Subscriptions: list, info, close
 *
 * @link https://help.unitpay.ru/api/subscription-list
 * @link https://help.unitpay.ru/api/subscription-info
 * @link https://help.unitpay.ru/api/close-subscription
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

// Active subscriptions of the project (add 'all' => 1 to include every status):
$list = $unitpay->api('listSubscriptions', ['projectId' => $projectId]);
var_dump($list->result ?? $list->error ?? $list);

$subscriptionId = 12345;

// Details of one subscription:
$info = $unitpay->api('getSubscription', ['subscriptionId' => $subscriptionId]);
var_dump($info->result ?? $info->error ?? $info);

// Close it (stops charges, unlinks the card — irreversible):
$closed = $unitpay->api('closeSubscription', ['subscriptionId' => $subscriptionId]);
if (isset($closed->result->message)) {
    print $closed->result->message;
} elseif (isset($closed->error->message)) {
    print 'Error: ' . $closed->error->message;
}
