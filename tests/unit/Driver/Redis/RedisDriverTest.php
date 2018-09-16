<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Redis;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\DevMeta\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group unit
 */
class RedisDriverTest extends Test
{
    /** @var \Redis|MockObject */
    private $redisMock;

    /** @var RedisDriver */
    private $driver;

    protected function setUp()
    {
        $this->redisMock = $this->createMock(\Redis::class);
        $this->driver = new RedisDriver($this->redisMock);
    }

    function testShouldCheckIfEntryExists()
    {
        $this->redisMock->expects($this->once())
            ->method('exists')
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->driver->exists('key'));
    }

    function testShouldRead()
    {
        $this->redisMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(serialize('value'));

        $this->assertSame('value', $this->driver->read('key', $exists));
        $this->assertTrue($exists);
    }

    function testShouldHandleReadFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(false);

        $this->assertNull($this->driver->read('key', $exists));
        $this->assertFalse($exists);
    }

    function testShouldReadMultiple()
    {
        $this->redisMock->expects($this->once())
            ->method('getMultiple')
            ->with(['foo', 'bar', 'baz'])
            ->willReturn([serialize('one'), false, serialize('three')]);

        $this->assertSameIterable(
            ['foo' => 'one', 'baz' => 'three'],
            $this->driver->readMultiple(['foo', 'bar', 'baz'])
        );
    }

    function testShouldWrite()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), ['nx'])
            ->willReturn(true);

        $this->driver->write('key', 'value');
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteWithTtl(?int $ttl, array $expectedTtlOptions)
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), $expectedTtlOptions + ['nx'])
            ->willReturn(true);

        $this->driver->write('key', 'value', $ttl);
    }

    function testShouldHandleWriteFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value');
    }

    function testShouldOverwrite()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), [])
            ->willReturn(true);

        $this->driver->write('key', 'value', null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteWithTtl(?int $ttl, array $expectedTtlOptions)
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), $expectedTtlOptions)
            ->willReturn(true);

        $this->driver->write('key', 'value', $ttl, true);
    }

    function testShouldWriteMultiple()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), ['nx']],
                ['baz', serialize('qux'), ['nx']]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteMultipleWithTtl(?int $ttl, array $expectedTtlOptions)
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), $expectedTtlOptions + ['nx']],
                ['baz', serialize('qux'), $expectedTtlOptions + ['nx']]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl);
    }

    function testShouldOverwriteMultiple()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), []],
                ['baz', serialize('qux'), []]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteMultipleWithTtl(?int $ttl, array $expectedTtlOptions)
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), $expectedTtlOptions],
                ['baz', serialize('qux'), $expectedTtlOptions]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl, true);
    }

    function testShouldHandleWriteMultipleFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), ['nx']],
                ['baz', serialize('qux'), ['nx']]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, false]);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entries at indexes: 1');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function testShouldWriteMultipleWithException()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('set')
            ->willThrowException(new \Exception('Dummy exception'));

        $this->redisMock->expects($this->once())
            ->method('discard');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dummy exception');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function provideTtl(): array
    {
        return [
            // ttl, expectedTtlOptions
            [123, ['ex' => 123]],
            [1, ['ex' => 1]],
            [0, []],
            [-1, []],
            [null, []],
        ];
    }

    function testShouldDelete()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('key')
            ->willReturn(1);

        $this->driver->delete('key');
    }

    function testShouldHandleDeleteFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('key')
            ->willReturn(0);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testShouldHandleDeleteMultiple()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('foo', 'bar', 'baz')
            ->willReturn(3);

        $this->driver->deleteMultiple(['foo', 'bar', 'baz']);
    }

    function testShouldHandleDeleteMultipleFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->willReturn(2);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete 1 out of 3 entries');

        $this->driver->deleteMultiple(['foo', 'bar', 'baz']);
    }

    function testShouldClear()
    {
        $this->redisMock->expects($this->once())
            ->method('flushDB')
            ->willReturn(true);

        $this->driver->clear();
    }

    function testShouldHandleClearFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('flushDB')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to flush DB');

        $this->driver->clear();
    }

    function testShouldFilter()
    {
        $this->redisMock->expects($this->once())
            ->method('keys')
            ->with('prefix_*')
            ->willReturn(['foo', 'bar', 'baz']);

        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('foo', 'bar', 'baz')
            ->willReturn(3);

        $this->driver->filter('prefix_');
    }

    function testShouldListKeys()
    {
        $this->redisMock->expects($this->once())
            ->method('keys')
            ->with('prefix_\?\*\[\]\^*')
            ->willReturn(['foo', 'bar', 'baz']);

        $this->assertSame(['foo', 'bar', 'baz'], $this->driver->listKeys('prefix_?*[]^'));
    }
}
