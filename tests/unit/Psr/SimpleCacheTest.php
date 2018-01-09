<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\CacheInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class SimpleCacheTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var CacheInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $cacheMock;
    /** @var SimpleCache */
    private $simpleCache;

    protected function setUp()
    {
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->simpleCache = new SimpleCache($this->cacheMock);
    }

    function testGet()
    {
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn('value');

        $this->assertSame('value', $this->simpleCache->get('key'));
    }

    function testGetWithCustomDefault()
    {
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(null);

        $this->assertSame('default', $this->simpleCache->get('key', 'default'));
    }

    function testSet()
    {
        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', 60)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->set('key', 'value', 60));
    }

    function testSetWithDateInterval()
    {
        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', 10)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->set('key', 'value', new \DateInterval('PT10S')));
    }

    function testSetWithNegativeDateInterval()
    {
        $ttl = new \DateInterval('PT10S');
        $ttl->invert = 1;

        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->set('key', 'value', $ttl));
    }

    function testDelete()
    {
        $this->cacheMock->expects($this->once())
            ->method('delete')
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->delete('key'));
    }

    function testClear()
    {
        $this->cacheMock->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->clear());
    }

    function testGetMultiple()
    {
        $this->cacheMock->expects($this->once())
            ->method('getMultiple')
            ->with(['foo', 'bar', 'baz'])
            ->willReturn(['foo' => 1, 'bar' => null, 'baz' => 2]);

        $this->assertSameIterable(
            ['foo' => 1, 'bar' => null, 'baz' => 2],
            $this->simpleCache->getMultiple(['foo', 'bar', 'baz'])
        );
    }

    function testGetMultipleWithCustomDefault()
    {
        $this->cacheMock->expects($this->once())
            ->method('getMultiple')
            ->with(['foo', 'bar'])
            ->willReturn(['foo' => 1, 'bar' => null]);

        $this->assertSameIterable(
            ['foo' => 1, 'bar' => 'default'],
            $this->simpleCache->getMultiple(['foo', 'bar'], 'default')
        );
    }

    function testSetMultiple()
    {
        $this->cacheMock->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => 1, 'bar' => 2], 60)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->setMultiple(['foo' => 1, 'bar' => 2], 60));
    }

    function testSetMultipleWithDateInterval()
    {
        $this->cacheMock->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => 1, 'bar' => 2], 30)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->setMultiple(['foo' => 1, 'bar' => 2], new \DateInterval('PT30S')));
    }

    function testSetMultipleWithNegativeDateInterval()
    {
        $ttl = new \DateInterval('PT30S');
        $ttl->invert = 1;

        $this->cacheMock->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => 1, 'bar' => 2], 0)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->setMultiple(['foo' => 1, 'bar' => 2], $ttl));
    }

    function testDeleteMultiple()
    {
        $this->cacheMock->expects($this->once())
            ->method('deleteMultiple')
            ->with(['foo', 'bar'])
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->deleteMultiple(['foo', 'bar']));
    }

    function testHas()
    {
        $this->cacheMock->expects($this->once())
            ->method('has')
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->has('key'));
    }
}
