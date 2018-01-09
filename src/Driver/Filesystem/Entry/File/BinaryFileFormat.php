<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

/**
 * Binary file format
 *
 * <int:expirationTime><int:dataLength><string:data><string:key>
 */
class BinaryFileFormat implements FileFormatInterface
{
    function validate(FileHandle $handle): bool
    {
        // a valid handle must contain at least the expiration time and data length
        // (data and key might be empty)
        return $handle->getRemaningBytes() >= $handle->getIntSize() * 2;
    }

    function readExpirationTime(FileHandle $handle): int
    {
        return $handle->readInt();
    }

    function readKey(FileHandle $handle): string
    {
        $handle->skipInt(); // skip expiration time
        $handle->move($handle->readInt()); // skip data

        return $handle->readString();
    }

    function readData(FileHandle $handle): string
    {
        $handle->skipInt(); // skip expiratiom time

        return $handle->readString($handle->readInt());
    }

    function write(FileHandle $handle, string $key, string $data, int $expirationTime): void
    {
        $handle->writeInt($expirationTime); // expiration time
        $handle->writeInt(strlen($data)); // data length
        $handle->writeString($data); // data
        $handle->writeString($key); // key
    }

    function getFilenameSuffix(): string
    {
        return '.dat';
    }
}
