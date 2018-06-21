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

    function testShouldGet()
    {
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn('value');

        $this->assertSame('value', $this->simpleCache->get('key'));
    }

    function testShouldGetValueWithCustomDefault()
    {
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(null);

        $this->assertSame('default', $this->simpleCache->get('key', 'default'));
    }

    function testShouldSet()
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
    function testShouldSetWithTtl($ttl, ?int $expectedTtlValue)
    {
        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', $expectedTtlValue)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->set('key', 'value', $ttl));
    }

    function testShouldDelete()
    {
        $this->cacheMock->expects($this->once())
            ->method('delete')
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->delete('key'));
    }

    function testShouldClear()
    {
        $this->cacheMock->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->clear());
    }

    function testShouldGetMultiple()
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

    function testShouldGetMultipleWithCustomDefault()
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

    function testShouldSetMultiple()
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
    function testShouldSetMultipleWithTtl($ttl, ?int $expectedTtlValue)
    {
        $this->cacheMock->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => 1, 'bar' => 2], $expectedTtlValue)
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->setMultiple(['foo' => 1, 'bar' => 2], $ttl));
    }

    function testShouldDeleteMultiple()
    {
        $this->cacheMock->expects($this->once())
            ->method('deleteMultiple')
            ->with(['foo', 'bar'])
            ->willReturn(true);

        $this->assertTrue($this->simpleCache->deleteMultiple(['foo', 'bar']));
    }

    function testShouldCheckIfEntryExists()
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
