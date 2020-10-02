<?php

declare(strict_types=1);

namespace Bohan\Symfony\PromiseHttpClient;

use GuzzleHttp\Promise\PromiseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface PromiseHttpClientInterface
{
    /**
     * Sends a request.
     *
     * The returned promise resolves a ResponseInterface
     * or fails with a TransportExceptionInterface.
     *
     * @see HttpClientInterface::request()
     */
    public function request(string $method, string $url, array $options = []) : PromiseInterface;
}
