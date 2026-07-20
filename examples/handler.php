<?php

/**
 * Демо-обработчик для ваших проектов.
 *
 * @link https://help.unitpay.ru/payments/payment-handler
 */

require_once('./orderInfo.php');
require_once('../UnitPay.php');

$unitpay = new UnitPay($domain, $secretKey);

// Держите белый список IP вебхуков актуальным БЕЗ сетевого запроса на каждый вызов:
// обновляйте его по расписанию (например, ежедневным cron) и кэшируйте результат —
//   $ips = (new UnitPay($domain, $secretKey))->refreshAllowedIps()->getAllowedIps();
// затем передавайте закэшированный список сюда, плюс свои IP (прокси/релей):
//   $unitpay->setAllowedIps($cachedIps)->addAllowedIps(['1.2.3.4']);

// Только для локальной отладки: доверяем 127.0.0.1, чтобы повторять вебхуки с этого хоста.
// addAllowedIps() добавляет его ПОВЕРХ списка Unitpay (setAllowedIps() заменил бы его).
// 127.0.0.1 по умолчанию не доверенный — за прокси на том же хосте REMOTE_ADDR равен
// 127.0.0.1 и обнулил бы проверку IP, — поэтому включайте это явным флагом и НИКОГДА
// не включайте в продакшене.
if (getenv('UNITPAY_DEBUG_LOCAL') === '1') {
    $unitpay->addAllowedIps(['127.0.0.1']);
}

try {
    // Проверяем запрос (IP отправителя, подпись, поддерживаемый метод).
    $unitpay->checkHandlerRequest();

    // Читаем проверенный запрос из SDK (учитывает подменённый запрос, а не $_GET).
    $method = $unitpay->getHandlerMethod();
    $params = $unitpay->getHandlerParams();

    // Очень важно: сверьте вебхук со своими данными заказа до завершения заказа.
    if (
        $params['orderSum'] != $orderSum ||
        $params['orderCurrency'] != $orderCurrency ||
        $params['account'] != $orderId ||
        $params['projectId'] != $projectId
    ) {
        throw new InvalidArgumentException('Order validation Error!');
    }

    switch ($method) {
        // 'check' — проверяем, что заказ можно оплатить (статус сервера, заказ в БД, ...).
        case 'check':
            print $unitpay->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
        // 'pay' — деньги получены; здесь завершаем заказ.
        case 'pay':
            print $unitpay->getSuccessHandlerResponse('Pay Success');
            break;
        // 'preauth' — двухстадийный платёж: деньги только ЗАБЛОКИРОВАНЫ, ещё не списаны.
        // НЕ выдавайте товары/услуги здесь; ждите 'pay'. Подтвердите приём, чтобы
        // уведомление не считалось неуспешным.
        case 'preauth':
            print $unitpay->getSuccessHandlerResponse('Preauth received. Funds held, awaiting capture.');
            break;
        // 'error' — произошла ошибка; залогируйте её.
        case 'error':
            print $unitpay->getSuccessHandlerResponse('Error logged');
            break;
    }
} catch (Exception $e) {
    // Любая ошибка (неверная подпись, недопустимый IP, расхождение заказа) вернёт ошибку в Unitpay.
    print $unitpay->getErrorHandlerResponse($e->getMessage());
}
