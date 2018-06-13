<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\Test\TimeMachine;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CacheItemTest extends TestCase
{
    function testShouldCreateCacheItem()
    {
        $item = new CacheItem('key', 'value', true);

        $this->assertSame('key', $item->getKey());
        $this->assertSame('value', $item->get());
        $this->assertTrue($item->isHit());
    }

    function testShouldCreateNewCacheItem()
    {
        $item = new CacheItem('foo', 123, false);

        $this->assertSame('foo', $item->getKey());
        $this->assertSame(123, $item->get());
        $this->assertFalse($item->isHit());
    }

    function testShouldSetValue()
    {
        $item = new CacheItem('key', 'value', false);
        $item->set(456);

        $this->assertSame(456, $item->get());
    }

    function testShouldGetDefaultTtl()
    {
        $this->assertNull(($item = new CacheItem('key', 'value', true))->getTtl());
    }

    /**
     * @dataProvider provideExpirationTimes
     */
    function testShouldExpireAt($expiration, int $now, ?int $expectedTtl)
    {
        TimeMachine::setTime([__NAMESPACE__], $now, function () use ($expiration, $expectedTtl) {
            $item = new CacheItem('key', 'value', true);
            $item->expiresAt($expiration);

            $this->assertSame($expectedTtl, $item->getTtl());
        });
    }

    function provideExpirationTimes(): array
    {
        return [
            // expiration, now, expectedTtl
            [new \DateTime('@123'), 100, 23],
            [new \DateTime('@20000'), 20010, -10],
            [null, 456, null],
        ];
    }

    /**
     * @dataProvider provideExpireAfterTimes
     */
    function testShouldExpireAfter($expireAfter, ?int $expectedTtl)
    {
        $item = new CacheItem('key', 'value', true);

        $this->assertSame($item, $item->expiresAfter($expireAfter));
        $this->assertSame($expectedTtl, $item->getTtl());
    }

    function provideExpireAfterTimes(): array
    {
        $negativeInterval = new \DateInterval('PT60S');
        $negativeInterval->invert = 1;

        return [
            // expireAfter, expectedTtl
            [60, 60],
            [123, 123],
            [0, 0],
            [-1, -1],
            [null, null],
            [new \DateInterval('PT60S'), 60],
            [$negativeInterval, null],
        ];
    }
}
