<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Exception\UnsupportedOperationException;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\ObservableTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CacheTest extends TestCase
{
    use ObservableTestTrait;
    use IterableAssertionTrait;

    /** @var Cache */
    private $cache;
    /** @var DriverInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $driverMock;

    protected function setUp()
    {
        $this->driverMock = $this->createMock(DriverInterface::class);
        $this->cache = new Cache($this->driverMock, 'prefix_');
    }

    function testGetDriver()
    {
        $this->assertSame($this->driverMock, $this->cache->getDriver());
    }

    function testPrefix()
    {
        $this->cache->setPrefix('custom_prefix');

        $this->assertSame('custom_prefix', $this->cache->getPrefix());
    }

    function testGetNamespace()
    {
        $namespacedCache = $this->cache->getNamespace('foo');

        $this->assertSame($this->cache, $namespacedCache->getWrappedCache());
    }

    /**
     * @dataProvider provideExistenceStates
     */
    function testHas($exists)
    {
        $this->driverMock->expects($this->once())
            ->method('exists')
            ->with('prefix_key')
            ->willReturn($exists);

        $this->assertSame($exists, $this->cache->has('key'));
    }

    function provideExistenceStates(): array
    {
        return [
            [true],
            [false],
        ];
    }

    function testHasFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('exists')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->has('key'));
    }

    function testGet()
    {
        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn('result');

        $this->expectEvent($this->cache, CacheEvents::READ, new CacheEvent('key', 'result'));

        $this->assertSame('result', $this->cache->get('key'));
    }

    function testGetValueOverrideThroughEvent()
    {
        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn('result');

        $this->cache->on(CacheEvents::READ, function (CacheEvent $e) {
            $this->assertSame('result', $e->value);

            $e->value = 'new-value';
        });

        $this->assertSame('new-value', $this->cache->get('key'));
    }

    function testGetFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('read')
            ->willThrowException($driverException);

        $this->expectNoEvent($this->cache, CacheEvents::READ);

        $this->assertNull($this->cache->get('key'));
    }

    function testGetMultiple()
    {
        $this->driverMock->expects($this->exactly(3))
            ->method('read')
            ->withConsecutive(
                ['prefix_foo'],
                ['prefix_bar'],
                ['prefix_baz']
            )
            ->willReturnOnConsecutiveCalls(
                1,
                null,
                2
            );

        $this->expectConsecutiveEvents(
            $this->cache,
            CacheEvents::READ,
            [new CacheEvent('foo', 1)],
            [new CacheEvent('bar', null)],
            [new CacheEvent('baz', 2)]
        );

        $this->assertSame(
            ['foo' => 1, 'bar' => null, 'baz' => 2],
            $this->cache->getMultiple(['foo', 'bar', 'baz'])
        );
    }

    function testGetMultipleWithNoKeys()
    {
        $this->expectNoEvent($this->cache, CacheEvents::READ);
        $this->expectNoEvent($this->cache, CacheEvents::DRIVER_EXCEPTION);

        $this->assertSameIterable([], $this->cache->getMultiple([]));
    }

    function testUnsupportedListKeys()
    {
        $this->assertFalse($this->cache->isFilterable());

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageRegExp('{Cannot list keys - the ".+" driver is not filterable}');

        foreach ($this->cache->listKeys() as $key) {
            $this->fail("Exception should have been thrown, but got a key: {$key}");
        }
    }

    function testAdd()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, null, false);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));

        $this->assertTrue($this->cache->add('foo', 123));
    }

    function testAddWithTtl()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, 60, false);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));

        $this->assertTrue($this->cache->add('foo', 123, 60));
    }

    function testAddValueOverrideThroughEvent()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 'new-value', null, false);

        $this->cache->on(CacheEvents::WRITE, function (CacheEvent $e) {
            $e->value = 'new-value';
        });

        $this->assertTrue($this->cache->add('foo', 123));
    }

    function testAddFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('write')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));
        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->add('foo', 123));
    }

    function testAddMultiple()
    {
        $this->driverMock->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                ['prefix_foo', 1, 60, false],
                ['prefix_bar', 2, 60, false],
                ['prefix_baz', 3, 60, false]
            );

        $this->expectConsecutiveEvents(
            $this->cache,
            CacheEvents::WRITE,
            [new CacheEvent('foo', 1)],
            [new CacheEvent('bar', 2)],
            [new CacheEvent('baz', 3)]
        );

        $this->assertTrue($this->cache->addMultiple(['foo' => 1, 'bar' => 2, 'baz' => 3], 60));
    }

    function testAddMultipleFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->at(0))
            ->method('write')
            ->with('prefix_foo', 1, 60, false)
            ->willThrowException($driverException);

        $this->driverMock->expects($this->at(1))
            ->method('write')
            ->with('prefix_bar', 2, 60, false);

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->addMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testAddMultipleWithEmptyIterable()
    {
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);
        $this->expectNoEvent($this->cache, CacheEvents::DRIVER_EXCEPTION);

        $this->assertTrue($this->cache->addMultiple([]));
    }

    function testSet()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, null, true);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));

        $this->assertTrue($this->cache->set('foo', 123));
    }

    function testSetWithTtl()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, 60, true);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));

        $this->assertTrue($this->cache->set('foo', 123, 60));
    }

    function testSetValueOverrideThroughEvent()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 'new-value', null, true);

        $this->cache->on(CacheEvents::WRITE, function (CacheEvent $e) {
            $e->value = 'new-value';
        });

        $this->assertTrue($this->cache->set('foo', 123));
    }

    function testSetFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('write')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('foo', 123));
        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->set('foo', 123));
    }

    function testSetMultiple()
    {
        $this->driverMock->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                ['prefix_foo', 1, 60, true],
                ['prefix_bar', 2, 60, true],
                ['prefix_baz', 3, 60, true]
            );

        $this->expectConsecutiveEvents(
            $this->cache,
            CacheEvents::WRITE,
            [new CacheEvent('foo', 1)],
            [new CacheEvent('bar', 2)],
            [new CacheEvent('baz', 3)]
        );

        $this->assertTrue($this->cache->setMultiple(['foo' => 1, 'bar' => 2, 'baz' => 3], 60));
    }

    function testSetMultipleFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->at(0))
            ->method('write')
            ->with('prefix_foo', 1, 60, true)
            ->willThrowException($driverException);

        $this->driverMock->expects($this->at(1))
            ->method('write')
            ->with('prefix_bar', 2, 60, true);

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->setMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testSetMultipleWithEmptyIterable()
    {
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);
        $this->expectNoEvent($this->cache, CacheEvents::DRIVER_EXCEPTION);

        $this->assertTrue($this->cache->setMultiple([]));
    }

    function testCachedRead()
    {
        $callback = function () {
            return 'fresh_value';
        };

        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn('cached_value');

        $this->driverMock->expects($this->never())
            ->method('write');

        $this->expectEvent($this->cache, CacheEvents::READ, new CacheEvent('key', 'cached_value'));
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);

        $this->assertSame('cached_value', $this->cache->cached('key', null, $callback));
    }

    function testCachedWrite()
    {
        $callback = function () {
            return 'value';
        };

        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn(null);

        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_key', 'value', null, false);

        $this->expectEvent($this->cache, CacheEvents::READ, new CacheEvent('key', null));
        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('key', 'value'));

        $this->assertSame('value', $this->cache->cached('key', null, $callback));
    }

    function testCachedOverwrite()
    {
        $callback = function () {
            return 'value';
        };

        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn(null);

        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_key', 'value', 123, true);

        $this->expectEvent($this->cache, CacheEvents::READ, new CacheEvent('key', null));
        $this->expectEvent($this->cache, CacheEvents::WRITE, new CacheEvent('key', 'value'));

        $this->assertSame('value', $this->cache->cached('key', 123, $callback, true));
    }

    function testCachedShouldNotWriteNull()
    {
        $callback = function () {
            return null;
        };

        $this->driverMock->expects($this->once())
            ->method('read')
            ->with('prefix_key')
            ->willReturn(null);

        $this->driverMock->expects($this->never())
            ->method('write');

        $this->expectEvent($this->cache, CacheEvents::READ, new CacheEvent('key', null));
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);

        $this->assertNull($this->cache->cached('key', null, $callback));
    }

    function testDelete()
    {
        $this->driverMock->expects($this->once())
            ->method('delete')
            ->with('prefix_foo');

        $this->assertTrue($this->cache->delete('foo'));
    }

    function testDeleteFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('delete')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->delete('foo'));
    }

    function testDeleteMultiple()
    {
        $this->driverMock->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                ['prefix_foo'],
                ['prefix_bar']
            );

        $this->assertTrue($this->cache->deleteMultiple(['foo', 'bar']));
    }

    function testDeleteMultipleFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->at(0))
            ->method('delete')
            ->with('prefix_foo')
            ->willThrowException($driverException);

        $this->driverMock->expects($this->at(1))
            ->method('delete')
            ->with('prefix_bar');

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->deleteMultiple(['foo', 'bar']));
    }

    function testDeleteMultipleWithNoKeys()
    {
        $this->expectNoEvent($this->cache, CacheEvents::DRIVER_EXCEPTION);

        $this->assertTrue($this->cache->deleteMultiple([]));
    }

    function testUnsupportedFilter()
    {
        $this->assertFalse($this->cache->isFilterable());

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageRegExp('{Cannot filter - the ".+" driver is not filterable}');

        $this->cache->filter('foo_');
    }

    function testClear()
    {
        $this->driverMock->expects($this->once())
            ->method('clear');

        $this->assertTrue($this->cache->clear());
    }

    function testClearFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('clear')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertFalse($this->cache->clear());
    }

    function testUnsupportedCleanup()
    {
        $this->assertFalse($this->cache->supportsCleanup());

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageRegExp('{The ".+" driver does not support the cleanup operation}');

        $this->cache->cleanup();
    }

    function testUnsupportedGetIterator()
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageRegExp('{Cannot list keys - the ".+" driver is not filterable}');

        iterator_to_array($this->cache);
    }
}
