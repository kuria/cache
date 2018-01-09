<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

/**
 * PHP file format
 *
 * Same as "BinaryFileFormat" but produces a no-op PHP script.
 */
class PhpFileFormat extends BinaryFileFormat
{
    function readExpirationTime(FileHandle $handle): int
    {
        $this->skipPhpHeader($handle);

        return parent::readExpirationTime($handle);
    }

    function readKey(FileHandle $handle): string
    {
        $this->skipPhpHeader($handle);

        return parent::readKey($handle);
    }

    function readData(FileHandle $handle): string
    {
        $this->skipPhpHeader($handle);

        return parent::readData($handle);
    }

    function write(FileHandle $handle, string $key, string $data, int $expirationTime): void
    {
        $handle->writeString('<?php __halt_compiler();');

        parent::write($handle, $key, $data, $expirationTime);
    }

    function getFilenameSuffix(): string
    {
        return '.php';
    }

    protected function skipPhpHeader(FileHandle $handle): void
    {
        $handle->move(24);
    }
}
