<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class IncrementalBackOff implements DelayStrategyInterface
{
    /** @var int */
    private $millis;

    public function __construct(int $millis)
    {
        if ($millis < 0) {
            throw new InvalidArgumentException(
                sprintf('Base time of delay in milliseconds must be greater than or equal to zero: "%s" given.', $millis)
            );
        }

        $this->millis = $millis;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : int
    {
        return $this->millis * $count;
    }
}
