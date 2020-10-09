# Make a Symfony HttpClient to return Guzzle promises

## Install

```sh
composer require bohan/promise-http-client
```

If you don't have a [symfony/http-client-implementation](https://packagist.org/providers/symfony/http-client-implementation) yet:

```sh
composer require symfony/http-client
```

## Example usage

```php
use Bohan\PromiseHttpClient\PromiseHttpClient;
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

## Credits

Most code of
 - [src/PromiseHttpClient.php](https://github.com/bohanyang/promise-http-client/blob/master/src/PromiseHttpClient.php)
 - [src/WaitLoop.php](https://github.com/bohanyang/promise-http-client/blob/master/src/WaitLoop.php)
 - [tests/PromiseHttpClientTest.php](https://github.com/bohanyang/promise-http-client/blob/master/tests/PromiseHttpClientTest.php)

comes from the [HttplugClient](https://github.com/symfony/http-client/blob/master/HttplugClient.php) of the [Symfony HttpClient component](https://github.com/symfony/http-client).
