<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface DelayStrategyInterface
{
    /**
     * @param int $count The number of retries, starts from 1
     * @return int The time to wait in milliseconds
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int;
}
