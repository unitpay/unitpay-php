<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Unitpay\Http\Response;

/**
 * The transport result object. Its whole reason to exist is that the old
 * `string|false` contract threw away the status code, the response headers and the
 * cURL errno, which made a timeout, a 404, a 500 and malformed JSON indistinguishable
 * to the caller.
 */
final class ResponseTest extends TestCase
{
    public function testReceivedCarriesStatusBodyAndHeaders(): void
    {
        $response = Response::received(200, '{"result":{}}', ['Content-Type: application/json']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"result":{}}', $response->getBody());
        $this->assertSame(['Content-Type: application/json'], $response->getHeaders());
        $this->assertSame(0, $response->getErrno());
        $this->assertSame('', $response->getTransportError());
    }

    /** A response that came back proves the request reached the server, whatever its status. */
    public function testReceivedAlwaysCountsAsRequestSent(): void
    {
        $this->assertTrue(Response::received(200, 'ok')->wasRequestSent());
        $this->assertTrue(Response::received(500, 'boom')->wasRequestSent());
    }

    public function testFailedHasNoStatusAndCarriesTheTransportError(): void
    {
        $response = Response::failed(7, 'Failed to connect to unitpay.ru port 443', false);

        $this->assertSame(0, $response->getStatusCode());
        $this->assertSame('', $response->getBody());
        $this->assertSame(7, $response->getErrno());
        $this->assertSame('Failed to connect to unitpay.ru port 443', $response->getTransportError());
    }

    /**
     * The retry-safety signal. cURL reports CURLE_OPERATION_TIMEDOUT (28) for both a
     * connect timeout and a read timeout, so the errno alone cannot tell whether the
     * server saw the request. Only wasRequestSent() may drive a retry decision — a
     * read timeout means the payment may already have been created.
     */
    public function testWasRequestSentDistinguishesConnectFromReadTimeout(): void
    {
        $connectTimeout = Response::failed(28, 'Connection timed out after 5001 milliseconds', false);
        $readTimeout = Response::failed(28, 'Operation timed out after 10001 milliseconds', true);

        $this->assertSame($connectTimeout->getErrno(), $readTimeout->getErrno());
        $this->assertFalse($connectTimeout->wasRequestSent());
        $this->assertTrue($readTimeout->wasRequestSent());
    }

    /**
     * @dataProvider successfulStatuses
     */
    public function testIsSuccessfulAcceptsTheWhole2xxRange(int $status): void
    {
        $this->assertTrue(Response::received($status, '')->isSuccessful());
    }

    /** @return array<string, array{int}> */
    public function successfulStatuses(): array
    {
        return [
            'status 200' => [200],
            'status 201' => [201],
            'status 204' => [204],
            'status 299' => [299],
        ];
    }

    /**
     * @dataProvider unsuccessfulStatuses
     */
    public function testIsSuccessfulRejectsEverythingOutside2xx(int $status): void
    {
        $this->assertFalse(Response::received($status, '')->isSuccessful());
    }

    /** @return array<string, array{int}> */
    public function unsuccessfulStatuses(): array
    {
        return [
            'status 199' => [199],
            'status 301' => [301],
            'status 404' => [404],
            'status 429' => [429],
            'status 500' => [500],
        ];
    }

    public function testFailedIsNeverSuccessful(): void
    {
        $this->assertFalse(Response::failed(6, 'Could not resolve host', false)->isSuccessful());
    }
}
