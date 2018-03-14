<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;
use Kuria\Cache\Test\TimeMachine;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class EntryTest extends TestCase
{
    use PHPMock;

    private const DUMMY_ENTRY_PATH = __DIR__ . '/dummy-entry-path';
    private const DUMMY_TMP_FILE = __DIR__ . '/dummy-tmp-path/file';

    /** @var FileFormatInterface|MockObject */
    private $fileFormatMock;
    /** @var MockObject|null */
    private $readHandleMock;
    /** @var Entry|MockObject */
    private $entry;

    protected function setUp()
    {
        $this->fileFormatMock = $this->createMock(FileFormatInterface::class);

        $this->readHandleMock = $this->createMock(FileHandle::class);

        $this->entry = $this->getMockBuilder(Entry::class)
            ->setConstructorArgs([$this->fileFormatMock, static::DUMMY_ENTRY_PATH, __DIR__ . '/dummy-tmp-path'])
            ->setMethods(['createReadHandle', 'writeToTemporaryFile', 'replaceFile'])
            ->getMock();

        $this->entry->method('createReadHandle')
            ->willReturnReference($this->readHandleMock);
    }

    function testGetPath()
    {
        $this->assertSame(static::DUMMY_ENTRY_PATH, $this->entry->getPath());
    }

    function testValidate()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(true);

        $this->fileFormatMock->expects($this->once())
            ->method('readExpirationTime')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(0);

        $this->assertTrue($this->entry->validate());
    }

    function testValidateWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->assertFalse($this->entry->validate());
    }

    function testValidateWithInvalidFile()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(false);

        $this->fileFormatMock->expects($this->never())
            ->method('readExpirationTime');

        $this->assertFalse($this->entry->validate());
    }

    function testValidateWithExpiredEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(true);

        TimeMachine::freezeTime([__NAMESPACE__], function (int $time) {
            $this->fileFormatMock->expects($this->once())
                ->method('readExpirationTime')
                ->with($this->identicalTo($this->readHandleMock))
                ->willReturn($time - 1);

            $this->assertFalse($this->entry->validate());
        });
    }

    function testReadKey()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readKey')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn('key');

        $this->assertSame('key', $this->entry->readKey());
    }

    function testReadKeyWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get read handle for ".+"}');

        $this->entry->readKey();
    }

    function testReadData()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readData')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn('data');

        $this->assertSame('data', $this->entry->readData());
    }

    function testReadDataWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get read handle for ".+"}');

        $this->entry->readData();
    }

    function testWrite()
    {
        $tmpPath = __DIR__ . '/dummy-tmp-path';

        $this->entry->expects($this->once())
            ->method('writeToTemporaryFile')
            ->with('key', 'data', 123)
            ->willReturn($tmpPath);

        $this->entry->expects($this->once())
            ->method('replaceFile')
            ->with($tmpPath);

        $this->entry->write('key', 'data', 123, true);
    }

    function testShouldOverwriteInvalidEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(false);

        $this->entry->expects($this->once())
            ->method('writeToTemporaryFile')
            ->with('key', 'data', 123)
            ->willReturn(static::DUMMY_TMP_FILE);

        $this->entry->expects($this->once())
            ->method('replaceFile')
            ->with(static::DUMMY_TMP_FILE);

        $this->entry->write('key', 'data', 123, false);
    }

    function testShouldNotOverwriteValidEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(true);

        $this->entry->expects($this->never())
            ->method('writeToTemporaryFile');

        $this->entry->expects($this->never())
            ->method('replaceFile');

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Entry ".+" already exists and is valid}');

        $this->entry->write('key', 'data', 123, false);
    }

    function testShouldDeleteEntry()
    {
        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with(static::DUMMY_ENTRY_PATH)
            ->willReturn(true);

        $this->entry->delete();
    }

    function testShouldThrowExceptionIfEntryDeletionFails()
    {
        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with(static::DUMMY_ENTRY_PATH)
            ->willReturn(false);

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Failed to delete file ".+"}');

        $this->entry->delete();
    }

    function testShouldCloseHandle()
    {
        $this->readHandleMock->expects($this->once())
            ->method('close');

        $this->fileFormatMock->expects($this->once())
            ->method('readKey')
            ->willReturn('key');

        $this->entry->readKey();
        $this->entry->close();
    }
}
