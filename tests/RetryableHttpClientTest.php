<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\Tests;

use Bohan\PromiseHttpClient\DelayStrategy\ConstantDelay;
use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Bohan\PromiseHttpClient\PromiseHttpClient;
use Bohan\PromiseHttpClient\PromiseHttpClientInterface;
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
    ) : PromiseHttpClientInterface
    {
        $client = new PromiseHttpClient(new MockHttpClient($responses, 'http://test'));
        $logger = new Logger('', [new StreamHandler('php://stderr')], [new PsrLogMessageProcessor()]);

        return new RetryableHttpClient($client, $retryStrategy, $delayStrategy, $maxRetries, $logger);
    }

    private static function delay(?int $ms) : DelayStrategyInterface
    {
        return new ConstantDelay($ms);
    }

    private static function retryOn(int $statusCode) : RetryStrategyInterface
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

    public function getMaxRetriesTestData() : iterable
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
    public function testMaxRetries(int $errors, int $maxRetries, bool $expectError) : void
    {
        $responses = [];

        for ($i = 0; $i < $errors; $i++) {
            $responses[] = new MockResponse('', ['http_code' => 503]);
        }

        $responses[] = new MockResponse('', ['http_code' => 200]);

        $client = self::mock($responses, self::retryOn(503), self::delay(0), $maxRetries);

        /** @var ResponseInterface $response */
        $response = $client->request('GET', __FUNCTION__)->wait();

        self::assertSame($expectError ? 503 : 200, $response->getStatusCode());
    }

    public function getRetryExceptionTestData() : iterable
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
    public function testRetryException($responses, \Exception $exception = null) : void
    {
        $strategy = new class implements RetryStrategyInterface {
            public function onResponse(ResponseInterface $response) : bool
            {
                return false;
            }

            public function onException(TransportExceptionInterface $exception) : bool
            {
                return $exception instanceof \RuntimeException;
            }
        };

        $client = self::mock($responses, $strategy, self::delay(0), 2);
        $promise = $client->request('GET', __FUNCTION__);

        if ($exception === null) {
            /** @var ResponseInterface $response */
            $response = $promise->wait();

            $this->assertSame(200, $response->getStatusCode());
        } else {
            $this->expectExceptionObject($exception);

            $promise->wait();
        }
    }
}
