<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
abstract class FileFormatTest extends TestCase
{
    /** @var FileFormatInterface */
    protected $format;

    /** @var FileHandle */
    protected $handle;

    protected function setUp()
    {
        $this->format = $this->createFormat();
        $this->handle = new FileHandle(fopen('php://memory', 'r+'));
    }

    abstract protected function createFormat(): FileFormatInterface;

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
}
