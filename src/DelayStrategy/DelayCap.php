<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DelayCap implements DelayStrategyInterface
{
    /** @var DelayStrategyInterface */
    private $strategy;

    /** @var int */
    private $max;

    /** @var bool */
    private $fallthrough;

    public function __construct(int $maxMs, DelayStrategyInterface $strategy, bool $fallthrough = false)
    {
        if ($maxMs < 0) {
            throw new InvalidArgumentException(\sprintf(
                'Maximum time of delay in milliseconds must be greater than zero: "%s" given.', $maxMs
            ));
        }

        $this->strategy = $strategy;
        $this->max = $maxMs;
        $this->fallthrough = $fallthrough;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int
    {
        $delay = $this->strategy->getDelay($count, $response);

        if ($delay === null) {
            return null;
        }

        if ($delay > $this->max) {
            return $this->fallthrough ? null : $this->max;
        }

        return $delay;
    }
}
