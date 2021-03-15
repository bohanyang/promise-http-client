<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ExponentialBackOff implements DelayStrategyInterface
{
    /** @var int */
    private $initial;

    /** @var float */
    private $multiplier;

    public function __construct(int $initialMs, float $multiplier)
    {
        if ($initialMs < 0) {
            throw new InvalidArgumentException(\sprintf(
                'Initial time of delay in milliseconds must be greater than or equal to zero: "%s" given.', $initialMs
            ));
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException(\sprintf(
                'Multiplier must be greater than or equal to one: "%s" given.', $multiplier
            ));
        }

        $this->initial = $initialMs;
        $this->multiplier = $multiplier;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : int
    {
        return (int) \round(
            $this->initial * $this->multiplier ** ($count - 1)
        );
    }
}
