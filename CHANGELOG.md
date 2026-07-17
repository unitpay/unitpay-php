# Changelog

### v2.1.0 от 21.07.2026
* CashItem: synced the 54-FZ dictionaries with the current backend
  * Added VAT rates: vat5, vat7, vat22 and the computed rates vat105, vat107, vat110, vat120, vat122
  * Added payment objects: payment_2, deposit, expense, pension_insurance_ip, pension_insurance, medical_insurance_ip, medical_insurance, social_insurance, casino_payment, issuance_bank, commodity_without_mark, commodity_mark
  * Deprecated (kept for BC, to be removed in 3.0) the values rejected by the public API: excise, gambling_bet, gambling_prize, lottery_prize, composite
* CashItem: added optional fields supported by the backend — sum, currency, measure (with MEASURE_* constants), nomenclatureCode, markCode, markQuantity, pre_text, post_text; setCashItems() now serializes them only when set
* api(): added coverage for refundPayment, confirmPayment, cancelPayment, listSubscriptions, getSubscription, closeSubscription, getMethodsAvailable, getCommissions, getCurrencyCourses, getPartner, offsetAdvance — with per-method required-param validation (secretKey injected automatically)
* api(): added payout coverage — massPayment, massPaymentStatus, massPaymentAvailableAmount, massPaymentCommissions, getSbpBankList, getBinInfo (all require login + account secretKey)
* api(): an explicit secretKey in the call params now overrides the constructor key, so account-level and payout methods can be called with the account key instead of the project key
* examples: added refund.php, twoStagePayment.php, subscription.php, payout.php and accountApi.php; orderInfo.php now also carries the account login + key for account-level/payout calls
* api(): aligned initPayment required params with the backend — account, sum, projectId, paymentType (secretKey is still enforced separately); desc is no longer forced
* api(): now sends flat query params (method=X&account=…&secretKey=…), the format Unitpay documents and accepts since 05/2026, instead of the legacy params[...] nesting (still accepted by the backend, so this is not a breaking change); the inbound webhook handler is unaffected and keeps reading params[...]
* api(): params set through the fluent setters (setCashItems, setCustomerEmail, setCustomerPhone, setBackUrl) now reach api() calls too, not only form() — so setCashItems()->api('initPayment', ...) actually sends the receipt; explicit api() params override the accumulated ones
* CashItem: the constructor now also rejects a non-numeric count or price (previously only 0/negative were caught) and normalizes numeric strings to int/float
* handler: reduced the IP whitelist to the officially published addresses (31.186.100.49, 51.250.20.9); 127.0.0.1 is NOT trusted by default (behind a same-host reverse proxy it would neuter the IP gate) — add it via setAllowedIps() for local debug; added setAllowedIps() to override the list
* handler: checkHandlerRequest() now accepts the `preauth` webhook (the two-stage hold notification Unitpay sends when funds are blocked but not yet captured) — it was previously rejected as an unsupported method, so two-stage/subscription handlers could never verify it
* examples/README: handle the initPayment "response" result type (e.g. recurring/subscription charges without a redirect)
* Added a PHPUnit test suite and injectable seams (getIp/API transport) for testing
* Added QA tooling: phpstan, php-cs-fixer, phpmd and parallel-lint
* Raised the minimum PHP requirement to 7.4
* handler: isAllowedIp() now matches CIDR subnets (IPv4/IPv6) as well as exact IPs, so setAllowedIps(['77.75.153.0/25']) works
* Added typed exceptions (UnitpaySignatureException, UnitpayIpException, UnitpayTransportException, UnitpayUnsupportedMethodException) implementing UnitpayExceptionInterface; each still extends the SPL exception it used to throw, so existing catch blocks keep working
* CashItem: the constructor now rejects a non-positive count or a negative price (behavioural change)
* api(): optional cURL transport with connect/read timeouts and no allow_url_fopen dependency (falls back to file_get_contents); ext-curl added to composer "suggest"
* api(): the cURL transport does not call curl_close() on PHP 8.0+, where it is a deprecated no-op that emits an E_DEPRECATED notice on PHP 8.5 on every API call; on PHP <8.0 the handle (a resource) is closed explicitly via a PHP_VERSION_ID guard for deterministic cleanup
* examples: removed the unreachable "refund" handler branch; added a "preauth" branch (two-stage hold notification — acknowledge receipt without delivering goods, which must wait for "pay"); orderInfo.php now reads the secret from the UNITPAY_SECRET_KEY environment variable instead of hardcoding it
* code-review hardening:
  * api(): folds only the fluent-setter params (cashItems/customerEmail/customerPhone/backUrl), not the whole param bag — a reused instance no longer leaks form()'s vital params or a stale signature into an unrelated api() call
  * setCashItems(): throws on a json_encode failure (e.g. a non-UTF-8 product name) instead of silently attaching an empty 54-FZ receipt
  * CashItem: keeps a fractional count (weight/volume goods) instead of truncating it to int
  * form(): throws when the secret is empty instead of returning an unsigned URL, consistent with api()/checkHandlerRequest()
  * getSignature()/api()/form(): format float params locale-independently so a comma-decimal locale on PHP <8.0 cannot corrupt the signature or amount
  * api(): a falsy explicit secretKey (e.g. an unset getenv()) falls back to the instance key instead of throwing
  * httpGet(): suppresses the file_get_contents failure warning so the secret-bearing URL is never written to the error log
  * checkHandlerRequest(): exposes the validated method/params via getHandlerMethod()/getHandlerParams() so consumers no longer re-read $_GET
  * every SDK exception now implements UnitpayExceptionInterface (added UnitpayValidationException for the missing-param/secret/method cases)

### v2.0.6 от 14.05.2025
* Added a new supported Unitpay IP address
* Updated README.md

### v2.0.5 от 04.02.2022
* Updated the list of Unitpay IP addresses
* Updated documentation links
* Code quality and structure cleanup

### v2.0.4 от 17.03.2021
* Updated getSignature method (2Garin)

### v2.0.3 от 20.02.2021
* Filter signature input parameters (strip sign/signature fields before signing)

### v2.0.2 от 31.08.2020
* Added nds, type and paymentMethod parameters to CashItem

### v2.0.1 от 03.03.2020
* Added domain selection to the examples

### v2.0.0 от 03.03.2020
* Added domain selection (configurable API domain)
* Updated the documentation URL

### v1.1.2 от 15.06.2018
* Fixed an array_merge exception ("Argument #1 is not an array") when no cash items are set

### v1.1.1 от 08.02.2018
* Added the LICENSE file
* Fixed the composer file

### v1.1.0 от 01.08.2017
* Added customerEmail, customerPhone and cashItems to the payment form

### v1.0.0 от 10.04.2017
* Initial public release of the Unitpay PHP SDK
* Switched to SHA-256 signatures for all methods (MD5 support removed)
* Added the getPayment API method and the orderInfo.php sample
* secretKey is now a required parameter for API calls
* Renamed billingCode to paymentType
* Deprecated statusUrl in favour of receiptUrl
* Added support for the partner handler method "error"
* Added an overridable getIp() method
