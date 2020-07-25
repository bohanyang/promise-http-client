<?php

declare(strict_types=1);

namespace Bohan\Symfony\PromiseHttpClient\Tests;

use Bohan\Symfony\PromiseHttpClient\PromiseHttpClient;
use Exception;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

use function get_class;

class PromiseClientTest extends TestCase
{
    public static function setUpBeforeClass() : void
    {
        TestHttpServer::start();
    }

    public function testRequest()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        /** @var ResponseInterface $response */
        $response = $client->request('GET', 'http://localhost:8057/')->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);

        $this->assertSame('HTTP/1.1', $response->toArray()['SERVER_PROTOCOL']);
    }

    public function testAsyncRequest()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $promise = $client->request('GET', 'http://localhost:8057/');

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise->then(
            function (ResponseInterface $response) use (&$successCallableCalled) {
                $successCallableCalled = true;

                return $response;
            },
            function (Exception $exception) use (&$failureCallableCalled) {
                $failureCallableCalled = true;

                throw $exception;
            }
        );

        $this->assertEquals(Promise::PENDING, $promise->getState());

        /** @var ResponseInterface $response */
        $response = $promise->wait(true);

        $this->assertTrue($successCallableCalled, '$promise->then() was never called.');
        $this->assertFalse($failureCallableCalled, 'Failure callable should not be called when request is successful.');

        $this->assertEquals(Promise::FULFILLED, $promise->getState());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);

        $this->assertSame('HTTP/1.1', $response->toArray()['SERVER_PROTOCOL']);
    }

    public function testWait()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $client->request('GET', 'http://localhost:8057/timeout-body')
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled) {
                    $successCallableCalled = true;

                    return $response;
                },
                function (Exception $exception) use (&$failureCallableCalled) {
                    $failureCallableCalled = true;

                    throw $exception;
                }
            );

        $client->wait(0);
        $this->assertFalse($successCallableCalled, '$promise->then() should not be called yet.');

        $client->wait();
        $this->assertTrue($successCallableCalled, '$promise->then() should have been called.');
        $this->assertFalse($failureCallableCalled, 'Failure callable should not be called when request is successful.');
    }

    public function testPostRequest()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        /** @var ResponseInterface $response */
        $response = $client->request(
            'POST',
            'http://localhost:8057/post',
            [
                'body' => 'foo=0123456789'
            ]
        )->wait();

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    public function testTransportException()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $this->expectException(TransportException::class);
        $client->request('GET', 'http://localhost:8058/')->wait();
    }

    public function testAsyncTransportException()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $promise = $client->request('GET', 'http://localhost:8058/');
        $successCallableCalled = false;
        $failureCallableCalled = false;
        $promise->then(
            function (ResponseInterface $response) use (&$successCallableCalled) {
                $successCallableCalled = true;

                return $response;
            },
            function (Exception $exception) use (&$failureCallableCalled) {
                $failureCallableCalled = true;

                throw $exception;
            }
        );

        $promise->wait(false);
        $this->assertFalse($successCallableCalled, 'Success callable should not be called when request fails.');
        $this->assertTrue($failureCallableCalled, 'Failure callable was never called.');
        $this->assertEquals(Promise::REJECTED, $promise->getState());

        $this->expectException(TransportException::class);
        $promise->wait(true);
    }

    public function testRequestException()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $client->request('BAD.METHOD', 'http://localhost:8057/')->wait();
    }

    public function testRetry404()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->request('GET', 'http://localhost:8057/404')
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled, $client) {
                    $this->assertSame(404, $response->getStatusCode());
                    $successCallableCalled = true;

                    return $client->request('GET', 'http://localhost:8057/');
                },
                function (Exception $exception) use (&$failureCallableCalled) {
                    $failureCallableCalled = true;

                    throw $exception;
                }
            );

        $response = $promise->wait(true);

        $this->assertTrue($successCallableCalled);
        $this->assertFalse($failureCallableCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetryNetworkError()
    {
        $client = new PromiseHttpClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->request('GET', 'http://localhost:8057/chunked-broken')
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled) {
                    $successCallableCalled = true;

                    return $response;
                },
                function (Exception $exception) use (&$failureCallableCalled, $client) {
                    $this->assertSame(TransportException::class, get_class($exception));
                    $failureCallableCalled = true;

                    return $client->request('GET', 'http://localhost:8057/');
                }
            );

        $response = $promise->wait(true);

        $this->assertFalse($successCallableCalled);
        $this->assertTrue($failureCallableCalled);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetryEarlierError()
    {
        $isFirstRequest = true;
        $errorMessage = 'Error occurred before making the actual request.';

        $client = new PromiseHttpClient(
            new MockHttpClient(
                function () use (&$isFirstRequest, $errorMessage) {
                    if ($isFirstRequest) {
                        $isFirstRequest = false;
                        throw new TransportException($errorMessage);
                    }

                    return new MockResponse('OK', ['http_code' => 200]);
                }
            )
        );

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->request('GET', 'http://test')
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled) {
                    $successCallableCalled = true;

                    return $response;
                },
                function (Exception $exception) use ($errorMessage, &$failureCallableCalled, $client) {
                    $this->assertSame(TransportException::class, get_class($exception));
                    $this->assertSame($errorMessage, $exception->getMessage());

                    $failureCallableCalled = true;

                    // Ensure arbitrary levels of promises work.
                    return (new FulfilledPromise(null))->then(
                        function () use ($client) {
                            return (new FulfilledPromise(null))->then(
                                function () use ($client) {
                                    return $client->request('GET', 'http://test');
                                }
                            );
                        }
                    );
                }
            );

        /** @var ResponseInterface $response */
        $response = $promise->wait(true);

        $this->assertFalse($successCallableCalled);
        $this->assertTrue($failureCallableCalled);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }
}
