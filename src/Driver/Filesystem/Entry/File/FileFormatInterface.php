<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

/**
 * All operations should assume that the handle is at the beginning.
 */
interface FileFormatInterface
{
    function validate(FileHandle $handle): bool;
    function readExpirationTime(FileHandle $handle): int;
    function readKey(FileHandle $handle): string;
    function readData(FileHandle $handle);
    function write(FileHandle $handle, string $key, $data, int $expirationTime): void;
    function getFilenameSuffix(): string;
}
