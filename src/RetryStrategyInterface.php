<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

interface RetryStrategyInterface
{
    public function onResponse(ResponseInterface $response) : bool;

    public function onException(TransportExceptionInterface $exception) : bool;
}
