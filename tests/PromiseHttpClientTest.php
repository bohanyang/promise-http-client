<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\Tests;

use Bohan\PromiseHttpClient\PromiseHttpClient;
use Bohan\PromiseHttpClient\PromiseHttpClientInterface;
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

final class PromiseHttpClientTest extends TestCase
{
    public static function setUpBeforeClass() : void
    {
        TestHttpServer::start();
    }

    private static function createClient() : PromiseHttpClientInterface
    {
        return new PromiseHttpClient(new NativeHttpClient(['base_uri' => 'http://127.0.0.1:8057']));
    }

    public function testRequest() : void
    {
        /** @var ResponseInterface $response */
        $response = self::createClient()->request('GET', '/')->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
        $this->assertSame('/', $response->toArray()['REQUEST_URI']);
    }

    public function testPromise() : void
    {
        $promise = ($client = self::createClient())->request('GET', '/');

        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            static function (ResponseInterface $response) use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;

                return $response;
            },
            static function (\Exception $exception) use (&$onRejectedCalled) {
                $onRejectedCalled = true;

                throw $exception;
            }
        );

        $this->assertEquals(Promise::PENDING, $promise->getState());

        /** @var ResponseInterface $response */
        $response = $promise->wait();

        $this->assertTrue($onFulfilledCalled);
        $this->assertFalse($onRejectedCalled);

        $this->assertEquals(Promise::FULFILLED, $promise->getState());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
        $this->assertSame('/', $response->toArray()['REQUEST_URI']);
    }

    public function testWait() : void
    {
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        ($client = self::createClient())->request('GET', '/timeout-body')->then(
            static function (ResponseInterface $response) use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;

                return $response;
            },
            static function (\Exception $exception) use (&$onRejectedCalled) {
                $onRejectedCalled = true;

                throw $exception;
            }
        );

        $client->wait(0);
        $this->assertFalse($onFulfilledCalled, '$onFulfilled should not be called yet.');

        $client->wait();
        $this->assertTrue($onFulfilledCalled, '$onFulfilled should have been called.');
        $this->assertFalse($onRejectedCalled, '$onRejected should not be called when request is successful.');
    }

    public function testPostRequest() : void
    {
        /** @var ResponseInterface $response */
        $response = self::createClient()
            ->request('POST', '/post', ['body' => 'foo=0123456789'])
            ->wait();

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    public function testTransportError() : void
    {
        $this->expectException(TransportException::class);
        self::createClient()->request('GET', 'http://127.0.0.1:8058/')->wait();
    }

    public function testRejectedPromise() : void
    {
        $promise = ($client = self::createClient())->request('GET', 'http://127.0.0.1:8058/');

        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            static function (ResponseInterface $response) use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;

                return $response;
            },
            static function (\Exception $exception) use (&$onRejectedCalled) {
                $onRejectedCalled = true;

                throw $exception;
            }
        );

        $promise->wait(false);
        $this->assertFalse($onFulfilledCalled);
        $this->assertTrue($onRejectedCalled);
        $this->assertEquals(Promise::REJECTED, $promise->getState());

        $this->expectException(TransportException::class);
        $promise->wait();
    }

    public function testInvalidRequest() : void
    {
        $this->expectException(InvalidArgumentException::class);
        self::createClient()->request('BAD.METHOD', '/')->wait();
    }

    public function testRetryHttpError() : void
    {
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise = ($client = self::createClient())->request('GET', '/404')->then(
            function (ResponseInterface $response) use (&$onFulfilledCalled, $client) {
                $this->assertSame(404, $response->getStatusCode());
                $onFulfilledCalled = true;

                return $client->request('GET', '/');
            },
            static function (\Exception $exception) use (&$onRejectedCalled) {
                $onRejectedCalled = true;

                throw $exception;
            }
        );

        $response = $promise->wait();

        $this->assertTrue($onFulfilledCalled);
        $this->assertFalse($onRejectedCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetryBrokenBody() : void
    {
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise = ($client = self::createClient())->request('GET', '/chunked-broken')->then(
            static function (ResponseInterface $response) use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;

                return $response;
            },
            function (\Exception $exception) use (&$onRejectedCalled, $client) {
                $this->assertSame(TransportException::class, \get_class($exception));
                $onRejectedCalled = true;

                return $client->request('GET', '/');
            }
        );

        $response = $promise->wait();

        $this->assertFalse($onFulfilledCalled);
        $this->assertTrue($onRejectedCalled);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetryEarlierError() : void
    {
        $isFirstRequest = true;
        $errorMessage = 'Error occurred before making the actual request.';

        $client = new PromiseHttpClient(
            new MockHttpClient(
                static function () use (&$isFirstRequest, $errorMessage) {
                    if ($isFirstRequest) {
                        $isFirstRequest = false;
                        throw new TransportException($errorMessage);
                    }

                    return new MockResponse('OK', ['http_code' => 200]);
                }
            )
        );

        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise = $client->request('GET', 'http://test')->then(
            static function (ResponseInterface $response) use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;

                return $response;
            },
            function (\Exception $exception) use ($errorMessage, &$onRejectedCalled, $client) {
                $this->assertSame(TransportException::class, \get_class($exception));
                $this->assertSame($errorMessage, $exception->getMessage());

                $onRejectedCalled = true;

                // Ensure arbitrary levels of promises work.
                return (new FulfilledPromise(null))->then(
                    static function () use ($client) {
                        return (new FulfilledPromise(null))->then(
                            static function () use ($client) {
                                return $client->request('GET', 'http://test');
                            }
                        );
                    }
                );
            }
        );

        /** @var ResponseInterface $response */
        $response = $promise->wait();

        $this->assertFalse($onFulfilledCalled);
        $this->assertTrue($onRejectedCalled);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function testDelay() : void
    {
        $client = self::createClient();

        $time = \microtime(true);
        $promise = $client->request('GET', '/', ['delay' => 1000]);
        $this->assertLessThan(0.5, \microtime(true) - $time);

        /** @var ResponseInterface $response */
        $response = $promise->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(1.0, \microtime(true) - $time);
    }
}
