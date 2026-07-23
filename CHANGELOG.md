# Changelog

### v2.1.0

* Телеметрия: пассивный анонимный фингерпринт версии (заголовки `User-Agent` и `X-Unitpay-Client` в `api()`, параметр `sdk` в URL `form()`) — самоидентификация SDK без дополнительных сетевых запросов и без PII; плюс опциональная (`enableTelemetry()`, по умолчанию выключена) best-effort отправка пре-флайт ошибок (неверная подпись, IP не в списке, нет обязательных параметров) на выводимый из `$domain` эндпоинт: шлёт только `sdk/php/error/method`, таймаут 300 мс, никогда не влияет на платёжный поток; глушится переменной окружения `UNITPAY_SDK_TELEMETRY_DISABLE=1`. Добавлена константа `UnitPay::VERSION`
* `CashItem`: справочники 54-ФЗ синхронизированы с бэкендом:
  * Добавлены ставки НДС: vat5, vat7, vat22 и расчётные vat105, vat107, vat110, vat120, vat122
  * Добавлены признаки предмета расчёта: payment_2, deposit, expense, pension_insurance_ip, pension_insurance, medical_insurance_ip, medical_insurance, social_insurance, casino_payment, issuance_bank, commodity_without_mark, commodity_mark
  * Помечены устаревшими (сохранены для обратной совместимости, удаление в 3.0) значения, отклоняемые публичным API: excise, gambling_bet, gambling_prize, lottery_prize, composite
* `CashItem`: добавлены опциональные поля бэкенда — sum, currency, measure (константы `MEASURE_*`), nomenclatureCode, markCode, markQuantity, pre_text, post_text; `setCashItems()` сериализует их только когда они заданы
* `api()`: добавлена поддержка методов refundPayment, confirmPayment, cancelPayment, listSubscriptions, getSubscription, closeSubscription, getMethodsAvailable, getCommissions, getCurrencyCourses, getPartner, offsetAdvance — с проверкой обязательных параметров для каждого (secretKey подставляется автоматически)
* `api()`: добавлены методы массовых выплат — massPayment, massPaymentStatus, massPaymentAvailableAmount, massPaymentCommissions, getSbpBankList, getBinInfo (требуют login и secretKey кабинета)
* `api()`: добавлены константы `PAYMENT_TYPE_*` для актуальных способов оплаты Unitpay (card, cardInvoice, sbp, sberpay, tinkoffpay, paypal, webmoney) — только для удобства и защиты от опечаток, `paymentType` по-прежнему передаётся без валидации; в README и примере `initPaymentApi` они заменили устаревшие qiwi/yandex/mc/alfaClick
* `api()`: явный secretKey в параметрах вызова переопределяет ключ из конструктора — методы уровня кабинета и выплат можно вызывать с ключом кабинета вместо ключа проекта
* `api()`: обязательные параметры initPayment приведены в соответствие с бэкендом — account, sum, projectId, paymentType (secretKey проверяется отдельно); desc больше не обязателен
* `api()`: параметры теперь отправляются плоско (`method=X&account=…&secretKey=…`), как Unitpay документирует и принимает с 05.2026, вместо устаревшей вложенности `params[...]` (по-прежнему принимается бэкендом, так что это не ломающее изменение); обработчик входящих вебхуков не затронут и продолжает читать `params[...]`
* `api()`: параметры из fluent-сеттеров (setCashItems, setCustomerEmail, setCustomerPhone, setBackUrl) теперь попадают и в вызовы `api()`, не только в `form()`; явные параметры `api()` имеют приоритет над накопленными
* `CashItem`: конструктор теперь отклоняет нечисловые count и price (раньше отлавливались только 0/отрицательные) и нормализует числовые строки в int/float
* `CashItem`: конструктор отклоняет неположительный count и отрицательный price (изменение поведения)
* `CashItem`: сохраняет дробный count (весовой/объёмный товар) вместо усечения до int
* handler: IP-белый список сокращён до официально опубликованных адресов (31.186.100.49, 51.250.20.9); 127.0.0.1 по умолчанию не доверенный (за обратным прокси на том же хосте он бы обнулил проверку по IP) — добавьте его через `setAllowedIps()` для локальной отладки; добавлен сам `setAllowedIps()` для переопределения списка
* handler: `isAllowedIp()` теперь сопоставляет не только точные IP, но и подсети CIDR (IPv4/IPv6) — работает `setAllowedIps(['77.75.153.0/25'])`
* handler: добавлен `refreshAllowedIps()` — подтягивает актуальный список IP вебхуков из публичного фида `/ips/ips_webhooks.json` и заменяет им встроенный (выведенный из эксплуатации IP выпадает автоматически); безопасен для сбоев: при любой ошибке транспорта/парсинга/валидации сохраняет встроенный список и не бросает исключение, поэтому его можно вызывать перед `checkHandlerRequest()`
* handler: добавлен `addAllowedIps()` — добавляет собственные IP/CIDR мерчанта (например, свой прокси/релей) поверх списка Unitpay; в отличие от `setAllowedIps()`, они сохраняются при `refreshAllowedIps()`/`setAllowedIps()`
* handler: добавлен `getAllowedIps()` — возвращает итоговый список (Unitpay + IP мерчанта); закешируйте его после `refreshAllowedIps()` и верните через `setAllowedIps()`, чтобы не обращаться к сети на каждый вебхук
* `UnitpayIpAllowlist::isValidEntry()` проверяет каждую загруженную запись IP/CIDR, поэтому битый JSON не может обнулить список
* handler: `checkHandlerRequest()` теперь принимает вебхук `preauth` (уведомление о холде при двухстадийной оплате, когда средства заблокированы, но ещё не списаны) — раньше он отклонялся как неподдерживаемый метод, из-за чего двухстадийные/подписочные обработчики не могли его проверить
* Добавлены типизированные исключения (UnitpaySignatureException, UnitpayIpException, UnitpayTransportException, UnitpayUnsupportedMethodException) с интерфейсом UnitpayExceptionInterface; каждое по-прежнему наследует прежний SPL-класс, поэтому существующие catch-блоки продолжают работать
* `api()`: опциональный транспорт cURL с таймаутами подключения/чтения и без зависимости от allow_url_fopen (откат на file_get_contents); ext-curl добавлен в composer "suggest"
* `api()`: транспорт cURL не вызывает curl_close() на PHP 8.0+ (там это устаревший no-op, вызывающий E_DEPRECATED на PHP 8.5 при каждом обращении к API); на PHP <8.0 хэндл (ресурс) закрывается явно через проверку PHP_VERSION_ID
* examples: полный набор сценариев — платёжная форма (`paymentForm`), API (`initPaymentApi`), чек 54-ФЗ через `CashItem` (`receipt`), вебхук (`webhook`), `getPayment` (`paymentInfo`), возврат (`refund`), двухстадийные (`twoStagePayment`), подписки (`subscriptions`), выплаты по СБП (`payout`), справочные вызовы кабинета (`accountInfo`), чек зачёта аванса (`offsetAdvance`); добавлен индекс `examples/README.md`
* examples: настройки подключения и данные заказа разделены — `config.php` (домен, ключи проекта/кабинета, login) и `order.php` (данные заказа); секреты читаются из окружения (`UNITPAY_SECRET_KEY`, `UNITPAY_LOGIN`, `UNITPAY_ACCOUNT_SECRET_KEY`) вместо хардкода
* examples: надёжность — `require` через `__DIR__` (не зависит от рабочего каталога), `exit` после `header('Location:')`, вызовы `api()`/`form()` обёрнуты в try/catch (`UnitpayExceptionInterface`); `webhook` отдаёт `application/json` и не оставляет пустой ответ на неизвестный метод (`default` в switch)
* examples: удалена недостижимая ветка обработчика "refund"; добавлена ветка "preauth" (уведомление о холде — подтверждаем получение, но товар не выдаём, это ждёт "pay"); обработка ответа типа "response" у initPayment (например, рекуррентные/подписочные списания без редиректа)
* Добавлен набор тестов PHPUnit и внедряемые точки (getIp/транспорт API) для тестирования
* Добавлены инструменты QA: phpstan, php-cs-fixer, phpmd и parallel-lint
* Нативные объявления типов: параметры, возвраты и типизированные свойства во всех трёх классах (`CashItem`, `UnitpayIpAllowlist`, `UnitPay`) в границах PHP 7.4 (без union-типов, `mixed` и `declare(strict_types)`) — публичный API и поведение не изменены. Денежные и количественные параметры (`form()` `$sum`, `CashItem` `$count`/`$price`, а также возврат `httpGet()` `string|false`) намеренно оставлены нетипизированными, чтобы сохранить прежнюю «мягкую» скалярную эргономику; их типы по-прежнему описаны в PHPDoc. Проверка PHPStan поднята с level 5 до level 6
* Минимальная версия PHP поднята до 7.4
* Усиление по итогам код-ревью:
  * `api()`: сворачивает только параметры fluent-сеттеров (cashItems/customerEmail/customerPhone/backUrl), а не весь набор — переиспользуемый экземпляр больше не протаскивает ключевые параметры `form()` или устаревшую подпись в посторонний вызов `api()`
  * `setCashItems()`: бросает исключение при ошибке json_encode (например, название товара не в UTF-8) вместо тихого прикрепления пустого чека 54-ФЗ
  * `CashItem`: сохраняет дробный count (весовой/объёмный товар) вместо усечения до int
  * `form()`: бросает исключение при пустом секрете вместо возврата неподписанного URL — как в `api()`/`checkHandlerRequest()`
  * `getSignature()`/`api()`/`form()`: форматируют float-параметры независимо от локали, чтобы локаль с запятой-разделителем на PHP <8.0 не испортила подпись или сумму
  * `api()`: пустой явный secretKey (например, несработавший getenv()) откатывается на ключ экземпляра вместо исключения
  * `httpGet()`: подавляет предупреждение file_get_contents, чтобы URL с секретом не попал в лог ошибок
  * `checkHandlerRequest()`: отдаёт проверенные method/params через `getHandlerMethod()`/`getHandlerParams()`, чтобы потребителю не перечитывать $_GET
  * каждое исключение SDK реализует UnitpayExceptionInterface (добавлено UnitpayValidationException для случаев отсутствующего параметра/секрета/метода)

### v2.0.6 от 14.05.2025

* Добавлен новый поддерживаемый IP-адрес Unitpay
* Обновлён README.md

### v2.0.5 от 04.02.2022

* Обновлён список IP-адресов Unitpay
* Обновлены ссылки на документацию
* Улучшены качество и структура кода

### v2.0.4 от 17.03.2021

* Обновлён метод `getSignature` (2Garin)

### v2.0.3 от 20.02.2021

* Фильтрация входных параметров подписи (удаление полей sign/signature перед подписанием)

### v2.0.2 от 31.08.2020

* Добавлены параметры nds, type и paymentMethod в `CashItem`

### v2.0.1 от 03.03.2020

* Добавлен выбор домена в примерах

### v2.0.0 от 03.03.2020

* Добавлен выбор домена (настраиваемый домен API)
* Обновлён URL документации

### v1.1.2 от 15.06.2018

* Исправлено исключение array_merge («Argument #1 is not an array»), когда чек не задан

### v1.1.1 от 08.02.2018

* Добавлен файл LICENSE
* Исправлен файл composer

### v1.1.0 от 01.08.2017

* Добавлены customerEmail, customerPhone и cashItems в платёжную форму

### v1.0.0 от 10.04.2017

* Первый публичный релиз Unitpay PHP SDK
* Переход на подписи SHA-256 для всех методов (поддержка MD5 удалена)
* Добавлен API-метод getPayment и пример orderInfo.php
* secretKey стал обязательным параметром для вызовов API
* billingCode переименован в paymentType
* statusUrl объявлен устаревшим в пользу receiptUrl
* Добавлена поддержка метода обработчика партнёра "error"
* Добавлен переопределяемый метод `getIp()`
