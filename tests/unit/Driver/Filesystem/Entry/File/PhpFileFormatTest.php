<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Test\Unexportable;
use Kuria\DevMeta\Test;

class PhpFileFormatTest extends Test
{
    private const TEST_FILE_PATH = __DIR__ . '/../../../../../temp.filesystem/php_file_format';

    /** @var FileFormatInterface */
    private $format;

    /** @var FileHandle */
    private $handle;

    protected function setUp()
    {
        if (!is_dir($testDir = dirname(self::TEST_FILE_PATH))) {
            mkdir($testDir, 0777, true);
        }

        $this->format = new PhpFileFormat();
        $this->handle = new FileHandle(self::TEST_FILE_PATH, fopen(self::TEST_FILE_PATH, 'w+'));
    }

    function testShouldValidateEmptyHandle()
    {
        $this->assertFalse($this->format->validate($this->handle));
    }

    function testShouldWriteAndRead()
    {
        $this->format->write($this->handle, 'key', 'data', 123);

        $this->handle->goto(0);
        $this->assertTrue($this->format->validate($this->handle));

        $this->handle->goto(0);
        $this->assertSame('key', $this->format->readKey($this->handle));

        $this->handle->goto(0);
        $this->assertSame('data', $this->format->readData($this->handle));

        $this->handle->goto(0);
        $this->assertSame(123, $this->format->readExpirationTime($this->handle));
    }

    function testShouldHandleRequireExceptions()
    {
        $this->format->write($this->handle, 'key', new Unexportable(), 0);
        $this->handle->goto(0);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An exception occured while reading data');

        $this->format->readData($this->handle);
    }

    function testShouldGetFilenameSuffix()
    {
        $this->assertSame('.php', $this->format->getFilenameSuffix());
    }
}
