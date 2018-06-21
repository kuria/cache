<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Memcached;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\TimeMachine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class MemcachedDriverTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var \Memcached|MockObject */
    private $memcachedMock;

    /** @var MemcachedDriver */
    private $driver;

    protected function setUp()
    {
        $this->memcachedMock = $this->createMock(\Memcached::class);
        $this->driver = new MemcachedDriver($this->memcachedMock);
    }

    /**
     * @dataProvider provideExistResultCodes
     */
    function testShouldCheckIfEntryExists(int $resultCode, bool $expectedResult)
    {
        $this->memcachedMock->expects($this->once())
            ->method('get')
            ->with('key');

        $this->prepareResultCode($resultCode);

        $this->assertSame($expectedResult, $this->driver->exists('key'));
    }

    function provideExistResultCodes(): array
    {
        return [
            [\Memcached::RES_SUCCESS, true],
            [\Memcached::RES_PAYLOAD_FAILURE, true],
            [\Memcached::RES_NOTFOUND, false],
        ];
    }

    function testShouldHandleExistenceCheckFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willThrowException(new \Exception());

        $this->assertFalse($this->driver->exists('key'));
    }

    function testShouldRead()
    {
        $this->memcachedMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn('value');

        $this->prepareResultCode(\Memcached::RES_SUCCESS);

        $this->assertSame('value', $this->driver->read('key', $exists));
        $this->assertTrue($exists);
    }

    function testShouldHandleReadFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('get')
            ->willReturn(false);

        $this->prepareResultCode(\Memcached::RES_NOTFOUND);

        $this->assertNull($this->driver->read('key', $exists));
        $this->assertFalse($exists);
    }

    function testShouldHandleReadException()
    {
        $this->memcachedMock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading the entry');

        $exists = 'initial';

        try {
            $this->driver->read('key', $exists);
        } finally {
            $this->assertSame('initial', $exists);
        }
    }

    function testShouldReadMultiple()
    {
        $this->memcachedMock->expects($this->once())
            ->method('getMulti')
            ->with(['foo', 'bar'])
            ->willReturn(['foo' => 1, 'bar' => 2]);

        $this->assertSame(['foo' => 1, 'bar' => 2], $this->driver->readMultiple(['foo', 'bar']));
    }

    function testShouldHandleReadMultipleFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('getMulti')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to read multiple entries');

        $this->driver->readMultiple(['foo', 'bar']);
    }

    function testShouldHandleReadMultipleException()
    {
        $this->memcachedMock->expects($this->once())
            ->method('getMulti')
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading multiple entries');

        $this->driver->readMultiple(['foo', 'bar']);
    }

    function testShouldWrite()
    {
        $this->memcachedMock->expects($this->once())
            ->method('add')
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value');
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteWithTtl(?int $ttl, int $now, int $expectedTtlValue)
    {
        TimeMachine::setTime(['Kuria\\Cache\\Driver\\Helper'], $now, function () use ($ttl, $expectedTtlValue) {
            $this->memcachedMock->expects($this->once())
                ->method('add')
                ->with('key', 'value', $expectedTtlValue)
                ->willReturn(true);

            $this->driver->write('key', 'value', $ttl);
        });
    }

    function testShouldHandleWriteFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('add')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value');
    }

    function testShouldOverwrite()
    {
        $this->memcachedMock->expects($this->once())
            ->method('set')
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value', null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteWithTtl(?int $ttl, int $now, int $expectedTtlValue)
    {
        TimeMachine::setTime(['Kuria\\Cache\\Driver\\Helper'], $now, function () use ($ttl, $expectedTtlValue) {
            $this->memcachedMock->expects($this->once())
                ->method('set')
                ->with('key', 'value', $expectedTtlValue)
                ->willReturn(true);

            $this->driver->write('key', 'value', $ttl, true);
        });
    }

    function testShouldHandleOverwriteFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('set')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value', null, true);
    }

    function testShouldWriteMultiple()
    {
        $this->memcachedMock->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                ['foo', 'bar', 0],
                ['baz', 'qux', 0]
            )
            ->willReturn(true);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteMultipleWithTtl(?int $ttl, int $now, int $expectedTtlValue)
    {
        TimeMachine::setTime(['Kuria\\Cache\\Driver\\Helper'], $now, function () use ($ttl, $expectedTtlValue) {
            $this->memcachedMock->expects($this->exactly(2))
                ->method('add')
                ->withConsecutive(
                    ['foo', 'bar', $expectedTtlValue],
                    ['baz', 'qux', $expectedTtlValue]
                )
                ->willReturn(true);

            $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl);
        });
    }

    function testShouldHandleWriteMultipleFailure()
    {
        $this->memcachedMock->expects($this->exactly(2))
            ->method('add')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write multiple entries');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function testShouldOverwriteMultiple()
    {
        $this->memcachedMock->expects($this->once())
            ->method('setMulti')
            ->with(['foo' => 'bar', 'baz' => 'qux'], 0)
            ->willReturn(true);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteMultipleWithTtl(?int $ttl, int $now, int $expectedTtlValue)
    {
        TimeMachine::setTime(['Kuria\\Cache\\Driver\\Helper'], $now, function () use ($ttl, $expectedTtlValue) {
            $this->memcachedMock->expects($this->once())
                ->method('setMulti')
                ->with(['foo' => 'bar', 'baz' => 'qux'], $expectedTtlValue)
                ->willReturn(true);

            $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl, true);
        });
    }

    function testShouldHandleOverwriteMultipleFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('setMulti')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write multiple entries');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    function provideTtl(): array
    {
        return [
            // ttl, now, expectedExpirationTime
            [1, 123, 124],
            [60, 1000, 1060],
            [null, 123, 0],
            [0, 123, 0],
            [-1, 123, 0],
        ];
    }

    function testShouldDelete()
    {
        $this->memcachedMock->expects($this->once())
            ->method('delete')
            ->with('key')
            ->willReturn(true);

        $this->driver->delete('key');
    }

    function testShouldHandleDeleteFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testShouldDeleteMultiple()
    {
        $this->memcachedMock->expects($this->once())
            ->method('deleteMulti')
            ->with(['foo', 'bar'])
            ->willReturn(['foo' => true, 'bar' => true]);

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testShouldHandleDeleteMultipleFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('deleteMulti')
            ->willReturn(['foo' => true, 'bar' => \Memcached::RES_NOTFOUND]);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entries: bar');

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testShouldClear()
    {
        $this->memcachedMock->expects($this->once())
            ->method('flush')
            ->willReturn(true);

        $this->driver->clear();
    }

    function testShouldHandleClearFailure()
    {
        $this->memcachedMock->expects($this->once())
            ->method('flush')
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to flush entries');

        $this->driver->clear();
    }

    private function prepareResultCode(int $resultCode): void
    {
        $this->memcachedMock->expects($this->once())
            ->method('getResultCode')
            ->willReturn($resultCode);
    }
}
