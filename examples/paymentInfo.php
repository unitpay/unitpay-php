<?php

header('Content-Type: text/html; charset=UTF-8');

/**
 * Payment info, with the 4.0 error surface spelled out.
 *
 * Every transport failure still extends UnitpayTransportException, so one catch is enough
 * when all you need is "it failed". Catch the subclasses when the answer changes what you
 * do next — the difference between "Unitpay refused this" and "the request never left the
 * server" is the difference between a support ticket and a retry.
 *
 * @link https://help.unitpay.ru/payments/payment-info
 */

use Unitpay\Exception\UnitpayExceptionInterface;
use Unitpay\Exception\UnitpayHttpException;
use Unitpay\Exception\UnitpayNetworkException;
use Unitpay\Exception\UnitpayResponseException;
use Unitpay\Unitpay;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$unitpay = new Unitpay($domain, $secretKey);

try {
    $response = $unitpay->payments()->getPayment(3403575);

    if (isset($response->result)) {
        var_dump($response->result);
    } elseif (isset($response->error->message)) {
        print 'Error: ' . $response->error->message;
    }
} catch (UnitpayHttpException $exception) {
    // Unitpay answered and refused. getResponseBody() holds what it actually said — a JSON
    // error envelope, or the HTML page a gateway serves on a 502. Quote that in a support
    // ticket, not just the status.
    printf(
        'Unitpay returned HTTP %d: %s',
        (int) $exception->getStatusCode(),
        (string) $exception->getResponseBody()
    );
} catch (UnitpayResponseException $exception) {
    // A 2xx whose body is not JSON — usually a captive portal or a proxy in the way.
    print 'Unexpected response body: ' . (string) $exception->getResponseBody();
} catch (UnitpayNetworkException $exception) {
    // No response at all. The message states whether the request was actually sent, which
    // is what decides whether repeating it is safe.
    print 'Could not reach Unitpay: ' . $exception->getMessage();
} catch (UnitpayExceptionInterface $exception) {
    // Everything else the SDK raises: a missing secret key, a bad argument.
    print 'SDK error: ' . $exception->getMessage();
}
