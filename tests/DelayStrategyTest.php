<?php

declare(strict_types=1);

namespace Bohan\PromiseHttpClient\Tests;

use Bohan\PromiseHttpClient\DelayStrategy\DelayCap;
use Bohan\PromiseHttpClient\DelayStrategy\DelayJitter;
use Bohan\PromiseHttpClient\DelayStrategy\ExponentialBackOff;
use Bohan\PromiseHttpClient\DelayStrategy\IncrementalBackOff;
use Bohan\PromiseHttpClient\DelayStrategy\RetryAfterHeader;
use Bohan\PromiseHttpClient\DelayStrategyInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DelayStrategyTest extends TestCase
{
    public function getExponentialBackOffTestData()
    {
        return [
            [0, 2.0, 2, 0],
            [3, 1.0, 2, 3],
            [500, 2.3, 3, 2645],
            [1000, 1.6, 4, 4096]
        ];
    }

    /** @dataProvider getExponentialBackOffTestData */
    public function testExponentialBackOff(int $millis, float $multiplier, int $count, int $expected)
    {
        $this->assertSame($expected, (new ExponentialBackOff($millis, $multiplier))->getDelay($count));
    }

    public function getIncrementalBackOffTestData()
    {
        return [
            [500, 1, 500],
            [500, 2, 1000]
        ];
    }

    /** @dataProvider getIncrementalBackOffTestData */
    public function testIncrementalBackOff(int $millis, int $count, int $expected)
    {
        $this->assertSame($expected, (new IncrementalBackOff($millis))->getDelay($count));
    }

    public function getDelayCapTestData()
    {
        return [
            [123, false, null, null],
            [321, true, null, null],
            [123, false, 321, 123],
            [123, true, 321, null],
            [0, false, 123, 0],
            [0, true, 123, null]
        ];
    }

    /** @dataProvider getDelayCapTestData */
    public function testDelayCap(int $max, bool $fallthrough, ?int $delay, ?int $expected)
    {
        $strategy = new DelayCap(
            $max,
            new class($delay) implements DelayStrategyInterface {
                private $delay;

                public function __construct(?int $delay)
                {
                    $this->delay = $delay;
                }

                public function getDelay(int $count, ResponseInterface $response = null) : ?int
                {
                    return $this->delay;
                }
            },
            $fallthrough
        );

        $this->assertSame($expected, $strategy->getDelay(2));
    }

    public function getRetryAfterHeaderTestData()
    {
        yield from [
            ['0', 0],
            [' 2 ', 2000],
            ['2.1', null],
            ['-1', null]
        ];

        $time = new \DateTimeImmutable('1 sec', new \DateTimeZone('UTC'));

        yield from [
            [$time->format('D, d M Y H:i:s') . ' GMT', 1000],
            [$time->format('D, d M Y H:i:s T'), null],
            [$time->format('Y-m-d H:i:s') . ' GMT', null]
        ];
    }

    /** @dataProvider getRetryAfterHeaderTestData */
    public function testRetryAfterHeader(string $value, ?int $expected)
    {
        $response = new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => $value]]);
        $this->assertSame($expected, (new RetryAfterHeader())->getDelay(2, $response));
    }

    public function getDelayJitterTestData()
    {
        $strategy = new ExponentialBackOff(100, 2.0);

        yield from [
            [0.2, $strategy, 1, 90, 120],
            [0.01, $strategy, 2, 199, 202],
            [0.0, $strategy, 3, 400, 400],
            [1.0, $strategy, 4, 400, 1600],
        ];
    }

    /** @dataProvider getDelayJitterTestData */
    public function testDelayJitter(float $factor, DelayStrategyInterface $strategy, int $count, int $from, int $to)
    {
        $strategy = new DelayJitter($factor, $strategy);
        $delay = $strategy->getDelay($count);
        $this->assertGreaterThanOrEqual($from, $delay);
        $this->assertLessThanOrEqual($to, $delay);
    }
}