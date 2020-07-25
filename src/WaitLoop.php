<?php

declare(strict_types=1);

namespace Bohan\Symfony\PromiseHttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function GuzzleHttp\Promise\queue;

/**
 * @see \Symfony\Component\HttpClient\Internal\HttplugWaitLoop
 *
 * @internal
 */
final class WaitLoop
{
    private $client;
    private $promisePool;

    public function __construct(HttpClientInterface $client, \SplObjectStorage $promisePool)
    {
        $this->client = $client;
        $this->promisePool = $promisePool;
    }

    public function wait(?ResponseInterface $pendingResponse, float $maxDuration = null, float $idleTimeout = null) : int
    {
        $guzzleQueue = queue();

        if (0.0 === $remainingDuration = $maxDuration) {
            $idleTimeout = 0.0;
        } elseif (null !== $maxDuration) {
            $startTime = \microtime(true);
            $idleTimeout = \max(0.0, \min($maxDuration / 5, $idleTimeout ?? $maxDuration));
        }

        do {
            foreach ($this->client->stream($this->promisePool, $idleTimeout) as $response => $chunk) {
                try {
                    if (null !== $maxDuration && $chunk->isTimeout()) {
                        goto check_duration;
                    }

                    if ($chunk->isFirst()) {
                        // Deactivate throwing on 3/4/5xx
                        $response->getStatusCode();
                    }

                    if (!$chunk->isLast()) {
                        goto check_duration;
                    }

                    if ($promise = $this->promisePool[$response] ?? null) {
                        unset($this->promisePool[$response]);
                        $promise->resolve($response);
                    }
                } catch (\Exception $e) {
                    if ($promise = $this->promisePool[$response] ?? null) {
                        unset($this->promisePool[$response]);
                        $promise->reject($e);
                    }
                }

                $guzzleQueue->run();

                if ($pendingResponse === $response) {
                    return $this->promisePool->count();
                }

                check_duration:
                if (null !== $maxDuration && $idleTimeout && $idleTimeout > $remainingDuration = max(0.0, $maxDuration - microtime(true) + $startTime)) {
                    $idleTimeout = $remainingDuration / 5;
                    break;
                }
            }

            if (!$count = $this->promisePool->count()) {
                return 0;
            }
        } while (null === $maxDuration || 0 < $remainingDuration);

        return $count;
    }
}
