<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

/**
 * @group unit
 */
class PhpFileFormatTest extends FileFormatTest
{
    protected function createFormat(): FileFormatInterface
    {
        return new PhpFileFormat();
    }

    function testWriteShouldPrependDataWithPhpHaltCompiler()
    {
        $this->format->write($this->handle, 'key', 'data', 123);
        $this->handle->goto(0);

        $this->assertSame('<?php __halt_compiler();', $this->handle->readString(24));
    }

    function testShouldGetFilenameSuffix()
    {
        $this->assertSame('.php', $this->format->getFilenameSuffix());
    }
}
