<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\Tests;

use Bohan\PromiseHttpClient\DelayStrategy\ConstantDelay;
use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Bohan\PromiseHttpClient\PromiseHttpClient;
use Bohan\PromiseHttpClient\RetryableHttpClient;
use Bohan\PromiseHttpClient\RetryStrategyInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class  RetryableHttpClientTest extends TestCase
{
    private static function mock(
        $responses,
        RetryStrategyInterface $retryStrategy,
        DelayStrategyInterface $delayStrategy,
        int $maxRetries
    )
    {
        return new RetryableHttpClient(
            new PromiseHttpClient(new MockHttpClient($responses, 'http://test')),
            $retryStrategy,
            $delayStrategy,
            $maxRetries,
            new Logger('', [new StreamHandler('php://stderr')], [new PsrLogMessageProcessor()])
        );
    }

    private static function delay(int $millis = 0) : DelayStrategyInterface
    {
        return new ConstantDelay($millis);
    }

    private static function retryOn(int $statusCode = 503) : RetryStrategyInterface
    {
        return new class($statusCode) implements RetryStrategyInterface {
            private $statusCode;

            public function __construct(int $statusCode)
            {
                $this->statusCode = $statusCode;
            }

            public function onResponse(ResponseInterface $response) : bool
            {
                return $response->getStatusCode() === $this->statusCode;
            }

            public function onException(TransportExceptionInterface $exception) : bool
            {
                return false;
            }
        };
    }

    public function getMaxRetriesTestData()
    {
        return [
            [2, 3, false],
            [3, 3, false],
            [4, 3, true],
            [0, 0, false],
            [1, 0, true]
        ];
    }

    /** @dataProvider getMaxRetriesTestData */
    public function testMaxRetries(int $errors, int $maxRetries, bool $expectError)
    {
        $responses = [];

        for ($i = 0; $i < $errors; $i++) {
            $responses[] = new MockResponse('', ['http_code' => 503]);
        }

        $responses[] = new MockResponse('', ['http_code' => 200]);

        $client = self::mock($responses, self::retryOn(503), self::delay(0), $maxRetries);

        /** @var ResponseInterface $response */
        $response = $client->request('GET', __FUNCTION__)->wait(true);

        self::assertSame($expectError ? 503 : 200, $response->getStatusCode());
    }

    public function getDelayTestData()
    {
        return [
            [1000, '1 sec'],
            [500, '500 ms']
        ];
    }

    /** @dataProvider getDelayTestData */
    public function testDelay(int $millis, string $expected)
    {
        $handler = function () use (&$expected) {
            if (\is_string($expected)) {
                $expected = new \DateTimeImmutable($expected);

                return new MockResponse('', ['http_code' => 503]);
            }

            $this->assertGreaterThan($expected, new \DateTimeImmutable());

            return new MockResponse('', ['http_code' => 200]);
        };

        $client = self::mock($handler, self::retryOn(503), self::delay($millis), 2);

        /** @var ResponseInterface $response */
        $response = $client->request('GET', __FUNCTION__)->wait(true);

        self::assertSame(200, $response->getStatusCode());
    }

    public function getRetryExceptionTestData()
    {
        $responses = [
            new MockResponse(['']),
            new MockResponse()
        ];

        yield [$responses];

        $isFirstRequest = true;
        $exception = new InvalidArgumentException('This exception should not be retried on.');

        $factory = function () use (&$isFirstRequest, $exception) {
            if ($isFirstRequest) {
                $isFirstRequest = false;

                throw $exception;
            }

            throw new TransportException('This exception should not be thrown.');
        };

        yield [$factory, $exception];
    }

    /** @dataProvider getRetryExceptionTestData */
    public function testRetryException($responses, \Exception $exception = null)
    {
        $strategy = new class implements RetryStrategyInterface {
            public function onResponse(ResponseInterface $response) : bool
            {
                return false;
            }

            public function onException(TransportExceptionInterface $exception) : bool
            {
                return !$exception instanceof InvalidArgumentException;
            }
        };

        $client = self::mock($responses, $strategy, self::delay(0), 2);
        $promise = $client->request('GET', __FUNCTION__);

        if ($exception === null) {
            /** @var ResponseInterface $response */
            $response = $promise->wait(true);

            $this->assertSame(200, $response->getStatusCode());
        } else {
            $this->expectExceptionObject($exception);

            $promise->wait(true);
        }
    }
}
