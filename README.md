# Make a Symfony HttpClient to return Guzzle promises

## Install

```shell script
composer require bohan/promise-http-client
```

If you don't have a [symfony/http-client-implementation](https://packagist.org/providers/symfony/http-client-implementation) yet:

```shell script
composer require symfony/http-client
```

## Example usage

```php
use Bohan\Symfony\PromiseHttpClient\PromiseHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

$client = new PromiseHttpClient(HttpClient::create());
$promise = $client->request('GET', 'https://httpbin.org/status/429')->then(function (ResponseInterface $response) use ($client) {
    if ($response->getStatusCode() < 300) {
        return $response;
    }

    return $client->request('GET', 'https://httpbin.org/get');
});

/** @var ResponseInterface $response */
$response = $promise->wait();

echo $response->getStatusCode(); // 200
```