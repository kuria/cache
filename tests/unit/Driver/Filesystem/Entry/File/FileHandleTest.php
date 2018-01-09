<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\FileHandleException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class FileHandleTest extends TestCase
{
    use PHPMock;

    function testOperations()
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        // initial state
        $this->assertTrue(is_resource($memoryHandle));
        $this->assertSame(0, $handle->getSize());
        $this->assertSame(0, $handle->getPosition());
        $this->assertSame(0, $handle->getRemaningBytes());

        // write
        $handle->writeInt(123456);
        $handle->writeString('foo_bar');

        $totalLength = $handle->getIntSize() + 7; // int length + string length

        $this->assertSame($totalLength, $handle->getSize());
        $this->assertSame($totalLength, $handle->getPosition());
        $this->assertSame(0, $handle->getRemaningBytes());

        // read
        $handle->goto(0);

        $this->assertSame(123456, $handle->readInt());
        $this->assertSame('foo_bar', $handle->readString(7));
        $this->assertSame(0, $handle->getRemaningBytes());

        $handle->goto(0);

        $this->assertSame('', $handle->readString(0));
        $this->assertSame(0, $handle->getPosition());
        $this->assertSame($totalLength, strlen($handle->readString()));
        $this->assertSame($totalLength, $handle->getPosition());

        // skip
        $handle->goto(0);
        $handle->skipInt();
        $handle->move(7);

        $this->assertSame($totalLength, $handle->getSize());
        $this->assertSame($totalLength, $handle->getPosition());
        $this->assertSame(0, $handle->getRemaningBytes());

        // truncate
        $handle->truncate();
        $handle->goto(0);

        $this->assertSame(0, $handle->getSize());
        $this->assertSame(0, $handle->getPosition());
        $this->assertSame(0, $handle->getRemaningBytes());

        // close
        $handle->close();

        $this->assertFalse(is_resource($memoryHandle));
    }

    /**
     * @dataProvider provideOperations
     */
    function testOperationFailures(string $method, ...$arguments)
    {
        $invalidHandle = fopen('php://memory', 'r+');
        fclose($invalidHandle);

        $handle = new FileHandle($invalidHandle);

        $this->expectException(FileHandleException::class);
        $this->expectExceptionMessage('Failed to');

        $handle->{$method}(...$arguments);
    }

    function provideOperations(): array
    {
        return [
            ['getSize'],
            ['getPosition'],
            ['goto', 10],
            ['move', 10],
            ['readInt'],
            ['writeInt', 123456],
            ['readString'],
            ['writeString', 'foo'],
            ['truncate'],
        ];
    }

    /**
     * @dataProvider provideLockModes
     */
    function testLocking(bool $exclusive, bool $block, int $expectedOperation)
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        $flockMock = $this->getFunctionMock(__NAMESPACE__, 'flock');

        $flockMock
            ->expects($this->at(0))
            ->with($this->identicalTo($memoryHandle), $expectedOperation)
            ->willReturn(true);

        $flockMock
            ->expects($this->at(1))
            ->with($this->identicalTo($memoryHandle), LOCK_UN)
            ->willReturn(true);

        $this->assertTrue($handle->lock($exclusive, $block));
        $this->assertTrue($handle->isLocked());
        $this->assertSame($exclusive, $handle->hasExclusiveLock());

        $this->assertTrue($handle->unlock());
        $this->assertFalse($handle->isLocked());
        $this->assertFalse($handle->hasExclusiveLock());
    }

    /**
     * @dataProvider provideLockModes
     */
    function testLockFailure(bool $exclusive, bool $block, int $expectedOperation)
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        $flockMock = $this->getFunctionMock(__NAMESPACE__, 'flock');

        $flockMock
            ->expects($this->once())
            ->with($this->identicalTo($memoryHandle), $expectedOperation)
            ->willReturn(false);

        $this->assertFalse($handle->lock($exclusive, $block));
        $this->assertFalse($handle->isLocked());
        $this->assertFalse($handle->hasExclusiveLock());
    }

    /**
     * @dataProvider provideLockModes
     */
    function testUnlockFailure(bool $exclusive, bool $block, int $expectedOperation)
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        $flockMock = $this->getFunctionMock(__NAMESPACE__, 'flock');

        $flockMock
            ->expects($this->at(0))
            ->with($this->identicalTo($memoryHandle), $expectedOperation)
            ->willReturn(true);

        $flockMock
            ->expects($this->at(1))
            ->with($this->identicalTo($memoryHandle), LOCK_UN)
            ->willReturn(false);

        $this->assertTrue($handle->lock($exclusive, $block));
        $this->assertTrue($handle->isLocked());
        $this->assertSame($exclusive, $handle->hasExclusiveLock());

        $this->assertFalse($handle->unlock());
        $this->assertTrue($handle->isLocked());
        $this->assertSame($exclusive, $handle->hasExclusiveLock());
    }

    /**
     * @dataProvider provideLockModes
     */
    function testAutomaticUnlockFromDestructor(bool $exclusive, bool $block, int $expectedOperation)
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        $flockMock = $this->getFunctionMock(__NAMESPACE__, 'flock');

        $flockMock
            ->expects($this->at(0))
            ->with($this->identicalTo($memoryHandle), $expectedOperation)
            ->willReturn(true);

        // called from destructor
        $flockMock
            ->expects($this->at(1))
            ->with($this->identicalTo($memoryHandle), LOCK_UN)
            ->willReturn(true);

        $this->assertTrue($handle->lock($exclusive, $block));

        unset($handle);
    }

    /**
     * @dataProvider provideLockModes
     */
    function testAutomaticUnlockAfterClose(bool $exclusive, bool $block, int $expectedOperation)
    {
        $memoryHandle = $this->createMemoryHandle();
        $handle = new FileHandle($memoryHandle);

        $flockMock = $this->getFunctionMock(__NAMESPACE__, 'flock');

        $flockMock
            ->expects($this->at(0))
            ->with($this->identicalTo($memoryHandle), $expectedOperation)
            ->willReturn(true);

        // called from destructor
        $flockMock
            ->expects($this->at(1))
            ->with($this->identicalTo($memoryHandle), LOCK_UN)
            ->willReturn(true);

        $this->assertTrue($handle->lock($exclusive, $block));

        $handle->close();

        $this->assertFalse($handle->isLocked());
        $this->assertFalse($handle->hasExclusiveLock());
    }

    function provideLockModes(): array
    {
        return [
            // exclusive, block, expectedOperation
            [false, false, LOCK_SH | LOCK_NB],
            [false, true, LOCK_SH],
            [true, false, LOCK_EX | LOCK_NB],
            [true, true, LOCK_EX],
        ];
    }

    private function createMemoryHandle()
    {
        return fopen('php://memory', 'r+');
    }
}
