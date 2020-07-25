<?php

declare(strict_types=1);

namespace Bohan\Symfony\PromiseHttpClient;

use GuzzleHttp\Promise\PromiseInterface;

interface PromiseHttpClientInterface
{
    /**
     * @return PromiseInterface
     * resolves a \Symfony\Contracts\HttpClient\ResponseInterface or
     * fails with a \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function request(string $method, string $url, array $options = []) : PromiseInterface;
}
