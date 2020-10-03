<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ConstantDelay implements DelayStrategyInterface
{
    /** @var int|null */
    private $millis;

    public function __construct(?int $millis)
    {
        if ($millis !== null && $millis < 0) {
            throw new InvalidArgumentException(
                sprintf('Time of delay in milliseconds must be greater than zero: "%s" given.', $millis)
            );
        }

        $this->millis = $millis;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int
    {
        return $this->millis;
    }
}
