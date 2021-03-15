<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class IncrementalBackOff implements DelayStrategyInterface
{
    /** @var int */
    private $initial;

    public function __construct(int $initialMs)
    {
        if ($initialMs < 0) {
            throw new InvalidArgumentException(\sprintf(
                'Initial time of delay in milliseconds must be greater than or equal to zero: "%s" given.', $initialMs
            ));
        }

        $this->initial = $initialMs;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : int
    {
        return $this->initial * $count;
    }
}
