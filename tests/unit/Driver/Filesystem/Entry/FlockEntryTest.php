<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;
use Kuria\Cache\Test\TimeMachine;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class FlockEntryTest extends TestCase
{
    use PHPMock;

    private const DUMMY_ENTRY_PATH = __DIR__ . '/dummy-entry-path';

    /** @var FileFormatInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $fileFormatMock;
    /** @var FileHandle|\PHPUnit_Framework_MockObject_MockObject|null */
    private $handleMock;
    /** @var FlockEntry */
    private $entry;

    protected function setUp()
    {
        $this->fileFormatMock = $this->createMock(FileFormatInterface::class);

        $this->handleMock = $this->createMock(FileHandle::class);

        $this->handleMock->method('isLocked')
            ->willReturn(true);
        $this->handleMock->method('hasExclusiveLock')
            ->willReturn(true);

        $this->entry = $this->getMockBuilder(FlockEntry::class)
            ->setConstructorArgs([$this->fileFormatMock, static::DUMMY_ENTRY_PATH])
            ->setMethods(['createHandle'])
            ->getMock();

        $this->entry->method('createHandle')
            ->willReturnReference($this->handleMock);
    }

    function testGetPath()
    {
        $this->assertSame(static::DUMMY_ENTRY_PATH, $this->entry->getPath());
    }

    function testValidate()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(true);

        $this->fileFormatMock->expects($this->once())
            ->method('readExpirationTime')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(0);

        $this->assertTrue($this->entry->validate());
    }

    function testValidateWithUnreadableFile()
    {
        $this->handleMock = null;

        $this->assertFalse($this->entry->validate());
    }

    function testValidateWithInvalidFile()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(false);

        $this->fileFormatMock->expects($this->never())
            ->method('readExpirationTime');

        $this->assertFalse($this->entry->validate());
    }

    function testValidateWithExpiredEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(true);

        TimeMachine::freezeTime([__NAMESPACE__], function (int $time) {
            $this->fileFormatMock->expects($this->once())
                ->method('readExpirationTime')
                ->with($this->identicalTo($this->handleMock))
                ->willReturn($time - 1);

            $this->assertFalse($this->entry->validate());
        });
    }

    function testReadKey()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readKey')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn('key');

        $this->assertSame('key', $this->entry->readKey());
    }

    function testReadKeyWithUnreadableFile()
    {
        $this->handleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get handle for ".+"}');

        $this->entry->readKey();
    }

    function testReadData()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readData')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn('data');

        $this->assertSame('data', $this->entry->readData());
    }

    function testReadDataWithUnreadableFile()
    {
        $this->handleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get handle for ".+"}');

        $this->entry->readData();
    }

    function testWrite()
    {
        $this->handleMock->expects($this->once())
            ->method('truncate');

        $this->fileFormatMock->expects($this->once())
            ->method('write')
            ->with($this->identicalTo($this->handleMock), 'key', 'data', 123);

        $this->entry->write('key', 'data', 123, true);
    }

    function testShouldOverwriteInvalidEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(false);

        $this->handleMock->expects($this->once())
            ->method('truncate');

        $this->fileFormatMock->expects($this->once())
            ->method('write')
            ->with($this->identicalTo($this->handleMock), 'key', 'data', 123);

        $this->entry->write('key', 'data', 123, false);
    }

    function testShouldNotOverwriteValidEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->handleMock))
            ->willReturn(true);

        $this->handleMock->expects($this->never())
            ->method('truncate');

        $this->fileFormatMock->expects($this->never())
            ->method('write');

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Entry ".+" already exists and is valid}');

        $this->entry->write('key', 'data', 123, false);
    }

    /**
     * @dataProvider provideOperations
     */
    function testShouldThrowExceptionIfAcquiringLockFails(string $method, ...$arguments)
    {
        $this->handleMock = $this->createMock(FileHandle::class);

        $this->handleMock->method('isLocked')
            ->willReturn(false);
        $this->handleMock->method('hasExclusiveLock')
            ->willReturn(false);
        $this->handleMock->method('lock')
            ->willReturn(false);

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Failed to acquire .+ lock}');

        $this->entry->{$method}(...$arguments);
    }

    function provideOperations(): array
    {
        return [
            ['validate'],
            ['readKey'],
            ['readData'],
            ['write', 'key', 'data', 123, true],
        ];
    }

    function testShouldThrowExceptionIfUpgradingLockFails()
    {
        $this->handleMock = $this->createMock(FileHandle::class);

        $this->handleMock->method('isLocked')
            ->willReturn(true);
        $this->handleMock->method('hasExclusiveLock')
            ->willReturn(false);
        $this->handleMock->method('lock')
            ->willReturn(false);

        $this->expectException(EntryException::class);
        $this->expectExceptionMessage('Failed to upgrade shared lock');

        $this->entry->write('key', 'data', 123, true);
    }

    function testShouldDeleteEntry()
    {
        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with(static::DUMMY_ENTRY_PATH)
            ->willReturn(true);

        $this->entry->delete();
    }

    function testShouldTruncateEntryIfDeletionFails()
    {
        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with(static::DUMMY_ENTRY_PATH)
            ->willReturn(false);

        $this->handleMock->expects($this->once())
            ->method('truncate')
            ->with(0)
            ->willReturn(true);

        $this->entry->delete();
    }

    function testShouldThrowExceptionIfCannotGetHandleToTruncateEntry()
    {
        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with(static::DUMMY_ENTRY_PATH)
            ->willReturn(false);

        $this->handleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Failed to delete entry ".+" - does not exist}');

        $this->entry->delete();
    }

    function testShouldCloseHandle()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readKey')
            ->willReturn('key');

        $this->handleMock->expects($this->once())
            ->method('close');

        $this->entry->readKey();
        $this->entry->close();
    }
}
