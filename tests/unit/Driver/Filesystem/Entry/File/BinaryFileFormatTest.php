<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\DevMeta\Test;

/**
 * @group unit
 */
class BinaryFileFormatTest extends Test
{
    /** @var FileFormatInterface */
    private $format;

    /** @var FileHandle */
    private $handle;

    protected function setUp()
    {
        $this->format = new BinaryFileFormat();
        $this->handle = new FileHandle('dummy', fopen('php://memory', 'r+'));
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

    function testShouldGetFilenameSuffix()
    {
        $this->assertSame('.dat', $this->format->getFilenameSuffix());
    }
}
