<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DelayStrategyChain implements DelayStrategyInterface
{
    /** @var DelayStrategyInterface[] */
    private $strategies;

    public function __construct(DelayStrategyInterface ...$strategies)
    {
        $this->strategies = $strategies;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int
    {
        foreach ($this->strategies as $strategy) {
            $delay = $strategy->getDelay($count, $response);

            if ($delay !== null) {
                return $delay;
            }
        }

        return null;
    }

    public static function createDefault() : self
    {
        return new self(
            new DelayCap(10000, new RetryAfterHeader(), true),
            new DelayCap(
                5000,
                new DelayJitter(
                    0.5,
                    new ExponentialBackOff(500, 1.5)
                )
            )
        );
    }
}
