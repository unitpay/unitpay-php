<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Подписки: список, информация, закрытие
 *
 * @link https://help.unitpay.ru/api/subscription-list
 * @link https://help.unitpay.ru/api/subscription-info
 * @link https://help.unitpay.ru/api/close-subscription
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

// Активные подписки проекта (добавьте 'all' => 1, чтобы включить все статусы).
$list = $unitpay->api('listSubscriptions', ['projectId' => $projectId]);
var_dump($list->result ?? $list->error ?? $list);

$subscriptionId = 12345;

$info = $unitpay->api('getSubscription', ['subscriptionId' => $subscriptionId]);
var_dump($info->result ?? $info->error ?? $info);

// Закрываем (прекращает списания, отвязывает карту — необратимо).
$closed = $unitpay->api('closeSubscription', ['subscriptionId' => $subscriptionId]);
if (isset($closed->result->message)) {
    print $closed->result->message;
} elseif (isset($closed->error->message)) {
    print 'Error: ' . $closed->error->message;
}
