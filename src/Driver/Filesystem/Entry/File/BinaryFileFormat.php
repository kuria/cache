<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\Cache\Driver\Helper\SerializationHelper;

/**
 * Binary file format
 *
 * Structure: <int:expirationTime><int:dataLength><string:data><string:key>
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

    function readData(FileHandle $handle)
    {
        $handle->skipInt(); // skip expiratiom time

        return SerializationHelper::smartUnserialize($handle->readString($handle->readInt()));
    }

    function write(FileHandle $handle, string $key, $data, int $expirationTime): void
    {
        $serializedData = serialize($data);

        $handle->writeInt($expirationTime); // expiration time
        $handle->writeInt(strlen($serializedData)); // data length
        $handle->writeString($serializedData); // data
        $handle->writeString($key); // key
    }

    function getFilenameSuffix(): string
    {
        return '.dat';
    }
}
