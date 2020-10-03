<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ExponentialBackOff implements DelayStrategyInterface
{
    /** @var int */
    private $millis;

    /** @var float */
    private $multiplier;

    public function __construct(int $millis, float $multiplier)
    {
        if ($millis < 0) {
            throw new InvalidArgumentException(
                \sprintf('Base time of delay in milliseconds must be greater than or equal to zero: "%s" given.', $millis)
            );
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException(
                \sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier)
            );
        }

        $this->millis = $millis;
        $this->multiplier = $multiplier;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : int
    {
        return (int) \round(
            $this->millis * $this->multiplier ** ($count - 1)
        );
    }
}
