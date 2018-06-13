<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Test\IterableAssertionTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class NamespacedCacheTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var CacheInterface|MockObject */
    private $wrappedCacheMock;
    /** @var NamespacedCache */
    private $namespacedCache;

    protected function setUp()
    {
        $this->wrappedCacheMock = $this->createMock(CacheInterface::class);
        $this->namespacedCache = new NamespacedCache($this->wrappedCacheMock, 'prefix_');
    }

    function testGetWrappedCache()
    {
        $this->assertSame($this->wrappedCacheMock, $this->namespacedCache->getWrappedCache());
    }

    function testPrefixConfiguration()
    {
        $this->assertSame('prefix_', $this->namespacedCache->getPrefix());

        $this->namespacedCache->setPrefix('foo_');

        $this->assertSame('foo_', $this->namespacedCache->getPrefix());
    }

    function testHas()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('has')
            ->with('prefix_key')
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->has('key'));
    }

    function testGet()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('get')
            ->with('prefix_key')
            ->willReturnCallback(function ($key, &$exists) {
                $exists = true;

                return 123;
            });

        $this->assertSame(123, $this->namespacedCache->get('key', $exists));
        $this->assertTrue($exists);
    }

    function testGetNonexistent()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('get')
            ->with('prefix_key')
            ->willReturnCallback(function ($key, &$exists) {
                $exists = false;

                return null;
            });

        $this->assertNull($this->namespacedCache->get('key', $exists));
        $this->assertFalse($exists);
    }

    function testGetMultiple()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('getMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar', 'prefix_baz']))
            ->willReturnCallback(function ($keys, &$failedKeys) {
                $failedKeys = ['prefix_bar'];

                return ['prefix_foo' => 1, 'prefix_bar' => null, 'prefix_baz' => 3];
            });

        $this->assertSame(
            ['foo' => 1, 'bar' => null, 'baz' => 3],
            $this->namespacedCache->getMultiple(['foo', 'bar', 'baz'], $failedKeys)
        );

        $this->assertSame(['bar'], $failedKeys);
    }

    function testListKeys()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('listKeys')
            ->with('prefix_foo_')
            ->willReturn(['prefix_foo_a', 'prefix_foo_b']);

        $this->assertSameIterable(['foo_a', 'foo_b'], $this->namespacedCache->listKeys('foo_'));
    }

    function testAdd()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('add')
            ->with('prefix_key', 123, 60)
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->add('key', 123, 60));
    }

    function testAddMultiple()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('addMultiple')
            ->with($this->isSameIterable(['prefix_foo' => 1, 'prefix_bar' => 2]), 60)
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->addMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testSet()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('set')
            ->with('prefix_key', 123, 60)
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->set('key', 123, 60));
    }

    function testSetMultiple()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('setMultiple')
            ->with($this->isSameIterable(['prefix_lorem' => 5, 'prefix_ipsum' => 6]), 60)
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->setMultiple(['lorem' => 5, 'ipsum' => 6], 60));
    }

    function testCached()
    {
        $callback = function () {};

        $this->wrappedCacheMock->expects($this->once())
            ->method('cached')
            ->with('prefix_key', 123, $this->identicalTo($callback), false);

        $this->namespacedCache->cached('key', 123, $callback);
    }

    function testCachedWithOverwrite()
    {
        $callback = function () {};

        $this->wrappedCacheMock->expects($this->once())
            ->method('cached')
            ->with('prefix_key', null, $this->identicalTo($callback), true);

        $this->namespacedCache->cached('key', null, $callback, true);
    }

    function testDelete()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('delete')
            ->with('prefix_key');

        $this->namespacedCache->delete('key');
    }

    function testDeleteMultiple()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('deleteMultiple')
            ->with($this->isSameIterable(['prefix_foo', 'prefix_bar']))
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->deleteMultiple(['foo', 'bar']));
    }

    function testFilter()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('isFilterable')
            ->willReturn(true);

        $this->wrappedCacheMock->expects($this->once())
            ->method('filter')
            ->with('prefix_foo_')
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->isFilterable());
        $this->assertTrue($this->namespacedCache->filter('foo_'));
    }

    function testClear()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->clear());
    }

    function testClearShouldUseFilterIfAvailable()
    {
        $this->wrappedCacheMock->method('isFilterable')
            ->willReturn(true);

        $this->wrappedCacheMock->expects($this->once())
            ->method('filter')
            ->with('prefix_')
            ->willReturn(true);

        $this->wrappedCacheMock->expects($this->never())
            ->method('clear');

        $this->assertTrue($this->namespacedCache->clear());
    }

    function testClearShouldNotUseFilterIfPrefixIsEmpty()
    {
        $this->wrappedCacheMock->method('isFilterable')
            ->willReturn(true);

        $this->wrappedCacheMock->expects($this->never())
            ->method('filter');

        $this->wrappedCacheMock->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->namespacedCache->setPrefix('');

        $this->assertTrue($this->namespacedCache->clear());
    }

    function testCleanup()
    {
        $this->wrappedCacheMock->expects($this->once())
            ->method('supportsCleanup')
            ->willReturn(true);

        $this->wrappedCacheMock->expects($this->once())
            ->method('cleanup')
            ->willReturn(true);

        $this->assertTrue($this->namespacedCache->supportsCleanup());
        $this->assertTrue($this->namespacedCache->cleanup());
    }

    function testGetIterator()
    {
        $iterator = new \ArrayIterator();

        $this->wrappedCacheMock->expects($this->once())
            ->method('getIterator')
            ->willReturn($iterator);

        $this->assertSame($iterator, $this->namespacedCache->getIterator());
    }
}
