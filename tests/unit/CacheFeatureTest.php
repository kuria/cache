<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Driver\Feature\CleanupInterface;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Feature\MultiDeleteInterface;
use Kuria\Cache\Driver\Feature\MultiReadInterface;
use Kuria\Cache\Driver\Feature\MultiWriteInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\ObservableTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CacheFeatureTest extends TestCase
{
    use ObservableTestTrait;
    use IterableAssertionTrait;

    function testGetMultiple()
    {
        $driver = $this->createDriverMock([MultiReadInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('readMultiple')
            ->with($this->isSameIterable([
                'prefix_foo',
                'prefix_bar',
                'prefix_null',
                'prefix_nonexistent',
                'prefix_nonexistent2',
            ]))
            ->willReturn([
                'prefix_foo' => 1,
                'prefix_bar' => 2,
                'prefix_null' => null,
            ]);

        $this->expectConsecutiveEvents(
            $cache,
            CacheEvents::HIT,
            ['foo', 1],
            ['bar', 2],
            ['null', null]
        );

        $this->expectConsecutiveEvents(
            $cache,
            CacheEvents::MISS,
            ['nonexistent'],
            ['nonexistent2']
        );

        $this->assertSameIterable(
            ['foo' => 1, 'bar' => 2, 'null' => null, 'nonexistent' => null, 'nonexistent2' => null],
            $cache->getMultiple(['foo', 'bar', 'null', 'nonexistent', 'nonexistent2'], $failedKeys)
        );

        $this->assertSame(['nonexistent', 'nonexistent2'], $failedKeys);
    }

    function testGetMultipleWithNokeys()
    {
        $driver = $this->createDriverMock([MultiReadInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->never())
            ->method('readMultiple');

        $this->assertSameIterable([], $cache->getMultiple([]));
    }

    function testGetMultipleFailure()
    {
        $driver = $this->createDriverMock([MultiReadInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('readMultiple')
            ->willThrowException($driverException);

        $this->expectNoEvent($cache, CacheEvents::HIT);
        $this->expectConsecutiveEvents($cache, CacheEvents::MISS, ['foo'], ['bar'], ['baz']);
        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertSameIterable(
            ['foo' => null, 'bar' => null, 'baz' => null],
            $cache->getMultiple(['foo', 'bar', 'baz'], $failedKeys)
        );

        $this->assertSame(['foo', 'bar', 'baz'], $failedKeys);
    }

    function testGetMultiplePartialFailure()
    {
        $driver = $this->createDriverMock([MultiReadInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('readMultiple')
            ->willReturnCallback(function () use ($driverException) {
                yield 'prefix_foo' => 1;
                yield 'prefix_bar' => 2;

                throw $driverException;
            });

        $this->expectConsecutiveEvents($cache, CacheEvents::HIT, ['foo', 1], ['bar', 2]);
        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));
        $this->expectEvent($cache, CacheEvents::MISS, 'baz');

        $this->assertSameIterable(
            ['foo' => 1, 'bar' => 2, 'baz' => null],
            $cache->getMultiple(['foo', 'bar', 'baz'], $failedKeys)
        );

        $this->assertSame(['baz'], $failedKeys);
    }

    function testListKeys()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('listKeys')
            ->with('prefix_foo_')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b', 'prefix_foo_c']);

        $this->assertSameIterable(['foo_a', 'foo_b', 'foo_c'], $cache->listKeys('foo_'));
    }

    function testListKeysWithNoPrefix()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver, '');

        $driver->expects($this->once())
            ->method('listKeys')
            ->with('foo_')
            ->willReturn(['foo_a', 'foo_b', 'foo_c']);

        $this->assertSameIterable(['foo_a', 'foo_b', 'foo_c'], $cache->listKeys('foo_'));
    }

    function testListKeysFailure()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('listKeys')
            ->willReturnCallback(function () use ($driverException) {
                yield 'prefix_foo_a';
                yield 'prefix_foo_b';

                throw $driverException;
            });

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertSameIterable(['foo_a', 'foo_b'], $cache->listKeys('foo_'));
    }

    function testAddMultiple()
    {
        $driver = $this->createDriverMock([MultiWriteInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 'foo-value', 'prefix_bar' => 'bar-value']), 60, false);

        $this->expectConsecutiveEvents(
            $cache,
            CacheEvents::WRITE,
            ['foo', 'foo-value', 60, false],
            ['bar', 'bar-value', 60, false]
        );

        $this->assertTrue($cache->addMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 60));
    }

    function testAddMultipleFailure()
    {
        $driver = $this->createDriverMock([MultiWriteInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 1, 'prefix_bar' => 2]), 60, false)
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->addMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testSetMultiple()
    {
        $driver = $this->createDriverMock([MultiWriteInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 'foo-value', 'prefix_bar' => 'bar-value']), 60, true);

        $this->expectConsecutiveEvents(
            $cache,
            CacheEvents::WRITE,
            ['foo', 'foo-value', 60, true],
            ['bar', 'bar-value', 60, true]
        );

        $this->assertTrue($cache->setMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 60));
    }

    function testSetMultipleFailure()
    {
        $driver = $this->createDriverMock([MultiWriteInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 1, 'prefix_bar' => 2]), 60, true)
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->setMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testDeleteMultiple()
    {
        $driver = $this->createDriverMock([MultiDeleteInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('deleteMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar']));

        $this->assertTrue($cache->deleteMultiple(['foo', 'bar']));
    }

    function testDeleteMultipleFailure()
    {
        $driver = $this->createDriverMock([MultiDeleteInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('deleteMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar']))
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->deleteMultiple(['foo', 'bar']));
    }

    function testFilter()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('filter')
            ->with('prefix_foo_');

        $this->assertTrue($cache->filter('foo_'));
    }

    function testFilterFailure()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('filter')
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->filter('foo_'));
    }

    function testCleanup()
    {
        $driver = $this->createDriverMock([CleanupInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('cleanup');

        $this->assertTrue($cache->cleanup());
    }

    function testCleanupFailure()
    {
        $driver = $this->createDriverMock([CleanupInterface::class]);
        $cache = $this->createCache($driver);
        $driverException = new DriverException();

        $driver->expects($this->once())
            ->method('cleanup')
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->cleanup());
    }

    function testClearShouldUseFilterIfAvailable()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('filter')
            ->with('prefix_');

        $driver->expects($this->never())
            ->method('clear');

        $this->assertTrue($cache->clear());
    }

    function testClearShouldNotUseFilterIfPrefixIsEmpty()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver, '');

        $driver->expects($this->never())
            ->method('filter');

        $driver->expects($this->once())
            ->method('clear');

        $this->assertTrue($cache->clear());
    }

    function testGetIterator()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('listKeys')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b', 'prefix_foo_c']);

        $driver->expects($this->exactly(3))
            ->method('read')
            ->withConsecutive(
                ['prefix_foo_a'],
                ['prefix_foo_b'],
                ['prefix_foo_c']
            )
            ->willReturnOnConsecutiveCalls(
                'a',
                null,
                'c'
            );

        $this->assertSameIterable(['foo_a' => 'a', 'foo_c' => 'c'], $cache->getIterator());
    }

    function testGetIteratorWithPrefix()
    {
        $driver = $this->createDriverMock([FilterableInterface::class]);
        $cache = $this->createCache($driver);

        $driver->expects($this->once())
            ->method('listKeys')
            ->with('prefix_foo_')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b']);

        $driver->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                ['prefix_foo_a'],
                ['prefix_foo_b']
            )
            ->willReturnOnConsecutiveCalls(
                'a',
                'b'
            );

        $this->assertSameIterable(['foo_a' => 'a', 'foo_b' => 'b'], $cache->getIterator('foo_'));
    }

    private function createCache($driver, string $prefix = 'prefix_'): Cache
    {
        return new Cache($driver, $prefix);
    }

    private function createDriverMock(array $driverInterfaces): MockObject
    {
        return $this->createMock(array_merge([DriverInterface::class], $driverInterfaces));
    }
}
