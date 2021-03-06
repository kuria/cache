<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;
use Kuria\DevMeta\Test;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group unit
 */
class EntryTest extends Test
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
            ->setConstructorArgs([$this->fileFormatMock, static::DUMMY_ENTRY_PATH, __DIR__ . '/dummy-tmp-path', 002])
            ->setMethods(['createReadHandle', 'writeToTemporaryFile', 'replaceFile'])
            ->getMock();

        $this->entry->method('createReadHandle')
            ->willReturnReference($this->readHandleMock);
    }

    function testShouldGetPath()
    {
        $this->assertSame(static::DUMMY_ENTRY_PATH, $this->entry->getPath());
    }

    function testShouldValidate()
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

    function testShouldValidateWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->assertFalse($this->entry->validate());
    }

    function testShouldValidateWithInvalidFile()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(false);

        $this->fileFormatMock->expects($this->never())
            ->method('readExpirationTime');

        $this->assertFalse($this->entry->validate());
    }

    function testShouldValidateWithExpiredEntry()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn(true);

        $this->atTime(1000, function () {
            $this->fileFormatMock->expects($this->once())
                ->method('readExpirationTime')
                ->with($this->identicalTo($this->readHandleMock))
                ->willReturn(999);

            $this->assertFalse($this->entry->validate());
        });
    }

    function testShouldReadKey()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readKey')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn('key');

        $this->assertSame('key', $this->entry->readKey());
    }

    function testShouldReadKeyWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get read handle for ".+"}');

        $this->entry->readKey();
    }

    function testShouldReadData()
    {
        $this->fileFormatMock->expects($this->once())
            ->method('readData')
            ->with($this->identicalTo($this->readHandleMock))
            ->willReturn('data');

        $this->assertSame('data', $this->entry->readData());
    }

    function testShouldReadDataWithUnreadableFile()
    {
        $this->readHandleMock = null;

        $this->expectException(EntryException::class);
        $this->expectExceptionMessageRegExp('{Cannot get read handle for ".+"}');

        $this->entry->readData();
    }

    function testShouldWrite()
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

    function testShouldDeleteTemporaryFileIfReplacementFailes()
    {
        $tmpPath = __DIR__ . '/dummy-tmp-path';

        $this->entry->expects($this->once())
            ->method('writeToTemporaryFile')
            ->with('key', 'data', 123)
            ->willReturn($tmpPath);

        $this->entry->expects($this->once())
            ->method('replaceFile')
            ->with($tmpPath)
            ->willThrowException(new EntryException('Test exception'));

        $this->getFunctionMock(__NAMESPACE__, 'unlink')
            ->expects($this->once())
            ->with($tmpPath)
            ->willReturn(true);

        $this->expectException(EntryException::class);
        $this->expectExceptionMessage('Test exception');

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
