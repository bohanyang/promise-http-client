<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DelayJitter implements DelayStrategyInterface
{
    /** @var float */
    private $factor;

    /** @var DelayStrategyInterface */
    private $strategy;

    public function __construct(float $factor, DelayStrategyInterface $strategy)
    {
        if ($factor < 0.0 || $factor > 1.0) {
            throw new InvalidArgumentException(\sprintf(
                'Factor must in between of zero and one: "%s" given.', $factor
            ));
        }

        $this->factor = $factor;
        $this->strategy = $strategy;
    }

    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int
    {
        if (null !== $delay = $this->strategy->getDelay($count, $response)) {
            $from = $this->strategy->getDelay($count - 1, $response);
            $to = $this->strategy->getDelay($count + 1, $response);
            $from = $delay - (int) \round(($delay - $from) * $this->factor);
            $to = $delay + (int) \round(($to - $delay) * $this->factor);
            $delay = \mt_rand($from, $to);
        }

        return $delay;
    }
}
