# Примеры

Готовые сценарии интеграции с Unitpay. Примеры читают `$_GET`/`$_SERVER` и вызывают
`header()`, поэтому их запускают по HTTP, а не из CLI:

```sh
php -S localhost:8000 -t examples
# затем откройте, например, http://localhost:8000/paymentInfo.php
```

## Настройки

Общие данные вынесены в два подключаемых файла (сами по себе не запускаются):

- [config.php](config.php) — подключение и ключи: `domain`, `projectId`, `publicId`,
  `secretKey`, а также `login`/`accountSecretKey` для методов уровня кабинета.
- [order.php](order.php) — данные заказа: `orderId`, `orderSum`, `orderDesc`,
  `orderCurrency`. Подключается примерами с оплатой в дополнение к `config.php`.

Секреты не хранятся в коде — читаются из окружения (с заглушками по умолчанию):

```sh
export UNITPAY_SECRET_KEY=...            # ключ проекта
export UNITPAY_LOGIN=...                 # login кабинета (методы уровня кабинета)
export UNITPAY_ACCOUNT_SECRET_KEY=...    # ключ кабинета
```

## Сценарии

| Файл | Сценарий |
| --- | --- |
| [paymentForm.php](paymentForm.php) | Платёжная форма на стороне Unitpay: `form()` строит URL на страницу оплаты; fluent-сеттеры (`setBackUrl`/`setCustomerEmail`/`setCustomerPhone`). |
| [initPaymentApi.php](initPaymentApi.php) | Server-to-server `initPayment`: обработка ответа `redirect` / `invoice` / `response`. |
| [receipt.php](receipt.php) | Фискальный чек по 54-ФЗ: позиции через `CashItem` + `setCashItems()`. |
| [webhook.php](webhook.php) | Обработчик вебхуков: проверка подписи и IP, ответы `check`/`pay`/`preauth`/`error`. |
| [paymentInfo.php](paymentInfo.php) | Информация о платеже (`getPayment`). |
| [refund.php](refund.php) | Возврат платежа, полный или частичный (`refundPayment`). |
| [twoStagePayment.php](twoStagePayment.php) | Двухстадийный платёж: `confirmPayment` (списание) / `cancelPayment` (разблокировка). |
| [subscriptions.php](subscriptions.php) | Подписки: список, информация, закрытие. |
| [payout.php](payout.php) | Выплаты (mass-payment) по СБП + статус. |
| [accountInfo.php](accountInfo.php) | Справочные вызовы (только для чтения): баланс, комиссии, курсы валют, BIN, способы оплаты. |
| [offsetAdvance.php](offsetAdvance.php) | Чек зачёта аванса (`offsetAdvance`) — создаёт фискальный чек по предоплате. |

## Обработчик вебхуков локально

По умолчанию `127.0.0.1` не доверенный. Только для локальной отладки повторов вебхука
с того же хоста включите его явным флагом (и **никогда** — в продакшене):

```sh
UNITPAY_DEBUG_LOCAL=1 php -S localhost:8000 -t examples
```
