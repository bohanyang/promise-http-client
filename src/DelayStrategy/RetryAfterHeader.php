<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\DelayStrategy;

use Bohan\PromiseHttpClient\DelayStrategyInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RetryAfterHeader implements DelayStrategyInterface
{
    /**
     * @inheritDoc
     */
    public function getDelay(int $count, ResponseInterface $response = null) : ?int
    {
        if ($response !== null) {
            $headers = $response->getHeaders(false);

            if (isset($headers['retry-after'][0])) {
                $seconds = self::parseToSeconds($headers['retry-after'][0]);

                if ($seconds !== null) {
                    return $seconds * 1000;
                }
            }
        }

        return null;
    }

    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Retry-After
    private static function parseToSeconds(string $value) : ?int
    {
        $seconds = (int) $value = \trim($value);

        if ($seconds >= 0) {
            if ($value === (string) $seconds) {
                return $seconds;
            }

            $value = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));

            if ($value) {
                return $value->getTimestamp() - \time();
            }
        }

        return null;
    }
}
