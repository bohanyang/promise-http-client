<?php

declare(strict_types=1);

namespace Bohan\Symfony\PromiseHttpClient;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use SplObjectStorage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PromiseHttpClient
{
    private $client;
    private $promisePool;
    private $waitLoop;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->promisePool = new SplObjectStorage();
        $this->waitLoop = new WaitLoop($this->client, $this->promisePool);
    }

    public function request(string $method, string $url, array $options = []): PromiseInterface
    {
        try {
            $response = $this->client->request($method, $url, $options);
        } catch (TransportExceptionInterface $exception) {
            return new RejectedPromise($exception);
        }

        $promisePool = $this->promisePool;
        $waitLoop = $this->waitLoop;

        $promise = new Promise(static function () use ($response, $waitLoop) {
            $waitLoop->wait($response);
        }, static function () use ($response, $promisePool) {
            $response->cancel();
            unset($promisePool[$response]);
        });

        $promisePool[$response] = $promise;

        return $promise;
    }

    /**
     * Resolves pending promises that complete before the timeouts are reached.
     *
     * When $maxDuration is null and $idleTimeout is reached, promises are rejected.
     *
     * @return int The number of remaining pending promises
     */
    public function wait(float $maxDuration = null, float $idleTimeout = null): int
    {
        return $this->waitLoop->wait(null, $maxDuration, $idleTimeout);
    }

    public function __destruct()
    {
        $this->wait();
    }
}
