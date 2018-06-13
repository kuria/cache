<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\CacheInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class SimpleCacheTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var CacheInterface|MockObject */
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
            ->with('key', 'value', null)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->set('key', 'value'));
    }

    /**
     * @dataProvider provideTtl
     */
    function testSetWithTtl($ttl, ?int $expectedTtlValue)
    {
        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', $expectedTtlValue)
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
            ->with(['foo' => 1, 'bar' => 2], null)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->setMultiple(['foo' => 1, 'bar' => 2]));
    }

    /**
     * @dataProvider provideTtl
     */
    function testSetMultipleWithTtl($ttl, ?int $expectedTtlValue)
    {
        $this->cacheMock->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => 1, 'bar' => 2], $expectedTtlValue)
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

    function provideTtl(): array
    {
        $negativeInterval = new \DateInterval('PT60S');
        $negativeInterval->invert = 1;

        return [
            // ttl, expectedTtlValue
            [60, 60],
            [123, 123],
            [0, 0],
            [-1, -1],
            [null, null],
            [new \DateInterval('PT60S'), 60],
            [new \DateInterval('PT1M5S'), 65],
            [$negativeInterval, null],
        ];
    }
}
