<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Exception\UnsupportedOperationException;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\ObservableTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
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
    /** @var DriverInterface|MockObject */
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
        $this->prepareDriverRead('prefix_key', 'result');
        $this->expectEvent($this->cache, CacheEvents::HIT, 'key', 'result');

        $this->assertSame('result', $this->cache->get('key', $exists));
        $this->assertTrue($exists);
    }

    function testGetNull()
    {
        $this->prepareDriverRead('prefix_key', null, true);
        $this->expectEvent($this->cache, CacheEvents::HIT, 'key', null);

        $this->assertNull($this->cache->get('key', $exists));
        $this->assertTrue($exists);
    }

    function testGetNonexistent()
    {
        $this->prepareDriverRead('prefix_key', null);
        $this->expectEvent($this->cache, CacheEvents::MISS, 'key');

        $this->assertNull($this->cache->get('key', $exists));
        $this->assertFalse($exists);
    }

    function testGetFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('read')
            ->willThrowException($driverException);

        $this->expectNoEvent($this->cache, CacheEvents::HIT);
        $this->expectNoEvent($this->cache, CacheEvents::MISS);
        $this->expectEvent($this->cache, CacheEvents::DRIVER_EXCEPTION, $this->identicalTo($driverException));

        $this->assertNull($this->cache->get('key', $exists));
        $this->assertFalse($exists);
    }

    function testGetMultiple()
    {
        $this->driverMock->expects($this->exactly(3))
            ->method('read')
            ->withConsecutive(
                ['prefix_value'],
                ['prefix_nonexistent'],
                ['prefix_null']
            )
            ->willReturnCallback(function ($key, &$exists) {
                switch ($key) {
                    case 'prefix_value':
                        $exists = true;

                        return 'some-value';

                    case 'prefix_baz':
                        $exists = true;

                        return 2;

                    case 'prefix_null':
                        $exists = true;

                        return null;

                    default:
                        $exists = false;

                        return null;
                }
            });

        $this->expectConsecutiveEvents(
            $this->cache,
            CacheEvents::HIT,
            ['value', 'some-value'],
            ['null', null]
        );

        $this->expectEvent($this->cache, CacheEvents::MISS, 'nonexistent');

        $this->assertSame(
            ['value' => 'some-value', 'nonexistent' => null, 'null' => null],
            $this->cache->getMultiple(['value', 'nonexistent', 'null'], $failedKeys)
        );

        $this->assertSame(['nonexistent'], $failedKeys);
    }

    function testGetMultipleWithNoKeys()
    {
        $this->expectNoEvents($this->cache);

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

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, null, false);

        $this->assertTrue($this->cache->add('foo', 123));
    }

    function testAddWithTtl()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, 60, false);

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, 60, false);

        $this->assertTrue($this->cache->add('foo', 123, 60));
    }

    function testAddFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('write')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, null, false);
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
            ['foo', 1, 60, false],
            ['bar', 2, 60, false],
            ['baz', 3, 60, false]
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
        $this->expectNoEvents($this->cache);

        $this->assertTrue($this->cache->addMultiple([]));
    }

    function testSet()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, null, true);

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, null, true);

        $this->assertTrue($this->cache->set('foo', 123));
    }

    function testSetWithTtl()
    {
        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_foo', 123, 60, true);

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, 60, true);

        $this->assertTrue($this->cache->set('foo', 123, 60));
    }

    function testSetFailure()
    {
        $driverException = new DriverException();

        $this->driverMock->expects($this->once())
            ->method('write')
            ->willThrowException($driverException);

        $this->expectEvent($this->cache, CacheEvents::WRITE, 'foo', 123, null, true);
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
            ['foo', 1, 60, true],
            ['bar', 2, 60, true],
            ['baz', 3, 60, true]
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
        $this->expectNoEvents($this->cache);

        $this->assertTrue($this->cache->setMultiple([]));
    }

    function testCachedRead()
    {
        $callback = function () {
            return 'fresh_value';
        };

        $this->prepareDriverRead('prefix_key', 'cached_value');

        $this->driverMock->expects($this->never())
            ->method('write');

        $this->expectEvent($this->cache, CacheEvents::HIT, 'key', 'cached_value');
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);

        $this->assertSame('cached_value', $this->cache->cached('key', null, $callback));
    }

    function testCachedReadNull()
    {
        $callback = function () {
            return 'fresh_value';
        };

        $this->prepareDriverRead('prefix_key', null, true);

        $this->driverMock->expects($this->never())
            ->method('write');

        $this->expectEvent($this->cache, CacheEvents::HIT, 'key', null);
        $this->expectNoEvent($this->cache, CacheEvents::WRITE);

        $this->assertNull($this->cache->cached('key', null, $callback));
    }

    function testCachedWrite()
    {
        $callback = function () {
            return 'value';
        };

        $this->prepareDriverRead('prefix_key', null);

        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_key', 'value', null, false);

        $this->expectEvent($this->cache, CacheEvents::MISS, 'key');
        $this->expectEvent($this->cache, CacheEvents::WRITE, 'key', 'value', null, false);

        $this->assertSame('value', $this->cache->cached('key', null, $callback));
    }

    function testCachedOverwrite()
    {
        $callback = function () {
            return 'value';
        };

        $this->prepareDriverRead('prefix_key', null);

        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_key', 'value', 123, true);

        $this->expectEvent($this->cache, CacheEvents::MISS, 'key');
        $this->expectEvent($this->cache, CacheEvents::WRITE, 'key', 'value', 123, true);

        $this->assertSame('value', $this->cache->cached('key', 123, $callback, true));
    }

    function testCachedShouldWriteNull()
    {
        $callback = function () {
            return null;
        };

        $this->prepareDriverRead('prefix_key', null);

        $this->driverMock->expects($this->once())
            ->method('write')
            ->with('prefix_key', null, null, false);

        $this->expectEvent($this->cache, CacheEvents::MISS, 'key');
        $this->expectEvent($this->cache, CacheEvents::WRITE, 'key', null, null, false);

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
        $this->expectNoEvents($this->cache);

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
        $this->expectExceptionMessageRegExp('{The ".+" driver does not support cleanup}');

        $this->cache->cleanup();
    }

    function testUnsupportedGetIterator()
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageRegExp('{Cannot list keys - the ".+" driver is not filterable}');

        iterator_to_array($this->cache);
    }

    private function prepareDriverRead(string $expectedKey, $result, ?bool $exists = null): void
    {
        $this->driverMock->expects($this->once())
            ->method('read')
            ->with($expectedKey)
            ->willReturnCallback(function ($key, &$existsRef) use ($result, $exists) {
                $existsRef = $exists ?? ($result !== null);

                return $result;
            });
    }
}
