<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\RetryStrategy;

use Bohan\PromiseHttpClient\RetryStrategyInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DefaultRetryStrategy implements RetryStrategyInterface
{
    /** @var int[] */
    private $statusCodes;

    public function __construct(array $statusCodes = [429, 500, 502, 503, 504])
    {
        $this->statusCodes = $statusCodes;
    }

    public function onResponse(ResponseInterface $response) : bool
    {
        return \in_array($response->getStatusCode(), $this->statusCodes, true);
    }

    public function onException(TransportExceptionInterface $exception) : bool
    {
        return !$exception instanceof InvalidArgumentException;
    }
}
