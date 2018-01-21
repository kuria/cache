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
use Kuria\Event\ObservableInterface;
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
        $cache = $this->createCache([MultiReadInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('readMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar', 'prefix_baz']))
            ->willReturn(['prefix_foo' => 1, 'prefix_bar' => 2, 'prefix_baz' => 3]);

        $this->expectConsecutiveEvents(
            $cache,
            CacheEvents::READ,
            [new CacheEvent('foo', 1)],
            [new CacheEvent('bar', 2)],
            [new CacheEvent('baz', 3)]
        );

        // test value override through event
        $cache->on(
            CacheEvents::READ,
            function (CacheEvent $e) {
                if ($e->key === 'foo') {
                    $e->value = 'new-value';
                } elseif ($e->key === 'baz') {
                    $e->value = null;
                }
            },
            -1
        );

        $this->assertSameIterable(
            ['foo' => 'new-value', 'bar' => 2, 'baz' => null],
            $cache->getMultiple(['foo', 'bar', 'baz'])
        );
    }

    function testGetMultipleWithNokeys()
    {
        $cache = $this->createCache([MultiReadInterface::class]);

        $cache->getDriver()->expects($this->never())
            ->method('readMultiple');

        $this->assertSameIterable([], $cache->getMultiple([]));
    }

    function testGetMultipleFailure()
    {
        $cache = $this->createCache([MultiReadInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('readMultiple')
            ->willThrowException($driverException);

        $this->expectNoEvent($cache, CacheEvents::READ);
        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertSameIterable(
            ['foo' => null, 'bar' => null, 'baz' => null],
            $cache->getMultiple(['foo', 'bar', 'baz'])
        );
    }

    function testListKeys()
    {
        $cache = $this->createCache([FilterableInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('listKeys')
            ->with('prefix_foo_')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b', 'prefix_foo_c']);

        $this->assertSameIterable(['foo_a', 'foo_b', 'foo_c'], $cache->listKeys('foo_'));
    }

    function testListKeysWithNoPrefix()
    {
        $cache = $this->createCache([FilterableInterface::class], '');

        $cache->getDriver()->expects($this->once())
            ->method('listKeys')
            ->with('foo_')
            ->willReturn(['foo_a', 'foo_b', 'foo_c']);

        $this->assertSameIterable(['foo_a', 'foo_b', 'foo_c'], $cache->listKeys('foo_'));
    }

    function testListKeysFailure()
    {
        $cache = $this->createCache([FilterableInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
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
        $cache = $this->createCache([MultiWriteInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 'foo-value', 'prefix_bar' => 'overriden-bar-value']), 60, false);

        $this->expectConsecutiveEvents($cache, CacheEvents::WRITE, [new CacheEvent('foo', 'foo-value')], [new CacheEvent('bar', 'bar-value')]);

        $cache->on(
            CacheEvents::WRITE,
            function (CacheEvent $e) {
                if ($e->key === 'bar') {
                    $e->value = 'overriden-bar-value';
                }
            },
            -1
        );

        $this->assertTrue($cache->addMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 60));
    }

    function testAddMultipleFailure()
    {
        $cache = $this->createCache([MultiWriteInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 1, 'prefix_bar' => 2]), 60, false)
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->addMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testSetMultiple()
    {
        $cache = $this->createCache([MultiWriteInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 'foo-value', 'prefix_bar' => 'overriden-bar-value']), 60, true);

        $this->expectConsecutiveEvents($cache, CacheEvents::WRITE, [new CacheEvent('foo', 'foo-value')], [new CacheEvent('bar', 'bar-value')]);

        $cache->on(
            CacheEvents::WRITE,
            function (CacheEvent $e) {
                if ($e->key === 'bar') {
                    $e->value = 'overriden-bar-value';
                }
            },
            -1
        );

        $this->assertTrue($cache->setMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 60));
    }

    function testSetMultipleFailure()
    {
        $cache = $this->createCache([MultiWriteInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('writeMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 1, 'prefix_bar' => 2]), 60, true)
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->setMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testDeleteMultiple()
    {
        $cache = $this->createCache([MultiDeleteInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('deleteMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar']));

        $this->assertTrue($cache->deleteMultiple(['foo', 'bar']));
    }

    function testDeleteMultipleFailure()
    {
        $cache = $this->createCache([MultiDeleteInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('deleteMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar']))
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->deleteMultiple(['foo', 'bar']));
    }

    function testFilter()
    {
        $cache = $this->createCache([FilterableInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('filter')
            ->with('prefix_foo_');

        $this->assertTrue($cache->filter('foo_'));
    }

    function testFilterFailure()
    {
        $cache = $this->createCache([FilterableInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('filter')
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->filter('foo_'));
    }

    function testCleanup()
    {
        $cache = $this->createCache([CleanupInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('cleanup');

        $this->assertTrue($cache->cleanup());
    }

    function testCleanupFailure()
    {
        $cache = $this->createCache([CleanupInterface::class]);
        $driverException = new DriverException();

        $cache->getDriver()->expects($this->once())
            ->method('cleanup')
            ->willThrowException($driverException);

        $this->expectEvent($cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($cache->cleanup());
    }

    function testClearShouldUseFilterIfAvailable()
    {
        $cache = $this->createCache([FilterableInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('filter')
            ->with($this->identicalTo('prefix_'));

        $cache->getDriver()->expects($this->never())
            ->method('clear');

        $this->assertTrue($cache->clear());
    }

    function testClearShouldNotUseFilterIfPrefixIsEmpty()
    {
        $cache = $this->createCache([FilterableInterface::class], '');

        $cache->getDriver()->expects($this->never())
            ->method('filter');

        $cache->getDriver()->expects($this->once())
            ->method('clear');

        $this->assertTrue($cache->clear());
    }

    function testGetIterator()
    {
        $cache = $this->createCache([FilterableInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('listKeys')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b', 'prefix_foo_c']);

        $cache->getDriver()->expects($this->exactly(3))
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
        $cache = $this->createCache([FilterableInterface::class]);

        $cache->getDriver()->expects($this->once())
            ->method('listKeys')
            ->with('prefix_foo_')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b']);

        $cache->getDriver()->expects($this->exactly(2))
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

    /**
     * @return CacheWithMockedDriver
     */
    private function createCache(array $driverInterfaces, string $prefix = 'prefix_'): Cache
    {
        /** @var DriverInterface $driver */
        $driver = $this->createMock(array_merge([DriverInterface::class], $driverInterfaces));

        return new Cache($driver, $prefix);
    }
}

/**
 * @internal
 */
interface CacheWithMockedDriver extends CacheInterface, ObservableInterface
{
    /**
     * @return DriverInterface|MockObject
     */
    function getDriver();
}
