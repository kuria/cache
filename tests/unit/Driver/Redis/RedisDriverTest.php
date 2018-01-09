<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Redis;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class RedisDriverTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var \Redis|\PHPUnit_Framework_MockObject_MockObject */
    private $redisMock;
    /** @var RedisDriver */
    private $driver;

    protected function setUp()
    {
        $this->redisMock = $this->createMock(\Redis::class);
        $this->driver = new RedisDriver($this->redisMock);
    }

    function testExists()
    {
        $this->redisMock->expects($this->once())
            ->method('exists')
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->driver->exists('key'));
    }

    function testRead()
    {
        $this->redisMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(serialize('value'));

        $this->assertSame('value', $this->driver->read('key'));
    }

    function testReadFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(false);

        $this->assertNull($this->driver->read('key'));
    }

    function testReadMultiple()
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

    function testWrite()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), ['nx'])
            ->willReturn(true);

        $this->driver->write('key', 'value');
    }

    function testWriteWithTtl()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), ['ex' => 123, 'nx'])
            ->willReturn(true);

        $this->driver->write('key', 'value', 123);
    }

    function testWriteFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value');
    }

    function testOverwrite()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), [])
            ->willReturn(true);

        $this->driver->write('key', 'value', null, true);
    }

    function testOverwriteWithTtl()
    {
        $this->redisMock->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'), ['ex' => 123])
            ->willReturn(true);

        $this->driver->write('key', 'value', 123, true);
    }

    function testWriteMultiple()
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

    function testWriteMultipleWithTtl()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), ['ex' => 123, 'nx']],
                ['baz', serialize('qux'), ['ex' => 123, 'nx']]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], 123);
    }

    function testOverwriteMultiple()
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

    function testOverwriteMultipleWithTtl()
    {
        $this->redisMock->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $this->redisMock->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['foo', serialize('bar'), ['ex' => 123]],
                ['baz', serialize('qux'), ['ex' => 123]]
            )
            ->willReturnSelf();

        $this->redisMock->expects($this->once())
            ->method('exec')
            ->willReturn([true, true]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], 123, true);
    }

    function testWriteMultipleFailure()
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

    function testWriteMultipleWithException()
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

    function testDelete()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('key')
            ->willReturn(1);

        $this->driver->delete('key');
    }

    function testDeleteFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('key')
            ->willReturn(0);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testDeleteMultiple()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->with('foo', 'bar', 'baz')
            ->willReturn(3);

        $this->driver->deleteMultiple(['foo', 'bar', 'baz']);
    }

    function testDeleteMultipleFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('del')
            ->willReturn(2);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete 1 out of 3 entries');

        $this->driver->deleteMultiple(['foo', 'bar', 'baz']);
    }

    function testClear()
    {
        $this->redisMock->expects($this->once())
            ->method('flushDB')
            ->willReturn(true);

        $this->driver->clear();
    }

    function testClearFailure()
    {
        $this->redisMock->expects($this->once())
            ->method('flushDB')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to flush DB');

        $this->driver->clear();
    }

    function testFilter()
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

    function testListKeys()
    {
        $this->redisMock->expects($this->once())
            ->method('keys')
            ->with('prefix_\?\*\[\]\^*')
            ->willReturn(['foo', 'bar', 'baz']);

        $this->assertSame(['foo', 'bar', 'baz'], $this->driver->listKeys('prefix_?*[]^'));
    }
}
