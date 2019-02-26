<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\Cache\Driver\Exception\DriverException;

/**
 * PHP file format
 *
 * Structure: <?php return <string:data>;__halt_compiler();<int:expirationTime><string:key><int:footerPosition>
 */
class PhpFileFormat implements FileFormatInterface
{
    function validate(FileHandle $handle): bool
    {
        // check minimal required size
        return $handle->getRemaningBytes() >= 32 + $handle->getIntSize() * 2;
    }

    function readExpirationTime(FileHandle $handle): int
    {
        $this->gotoFooter($handle);

        return $handle->readInt();
    }

    function readKey(FileHandle $handle): string
    {
        $this->gotoFooter($handle);
        $handle->skipInt(); // skip expiration time

        return $handle->readString($handle->getRemaningBytes() - $handle->getIntSize()); // read key without footer position
    }

    function readData(FileHandle $handle)
    {
        try {
            // must suppress errors due to __halt_compiler() generating notices on multiple requires under some conditions
            return @require $handle->getPath();
        } catch (\Throwable $e) {
            throw new DriverException(sprintf('An exception occured while reading data from "%s"', $handle->getPath()), 0, $e);
        }
    }

    function write(FileHandle $handle, string $key, $data, int $expirationTime): void
    {
        $handle->writeString('<?php return ');
        $handle->writeString(var_export($data, true));
        $handle->writeString(';__halt_compiler();');

        $footerPosition = $handle->getPosition();

        $handle->writeInt($expirationTime);
        $handle->writeString($key);
        $handle->writeInt($footerPosition);
    }

    function getFilenameSuffix(): string
    {
        return '.php';
    }

    private function gotoFooter(FileHandle $handle): void
    {
        $handle->goto($handle->getSize() - $handle->getIntSize()); // go to footer position int
        $handle->goto($handle->readInt()); // read and go to footer position
    }
}
