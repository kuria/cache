<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

/**
 * @group unit
 */
class BinaryFileFormatTest extends FileFormatTest
{
    protected function createFormat(): FileFormatInterface
    {
        return new BinaryFileFormat();
    }

    function testGetFilenameSuffix()
    {
        $this->assertSame('.dat', $this->format->getFilenameSuffix());
    }
}
