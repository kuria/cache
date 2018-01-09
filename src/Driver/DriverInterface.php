<?php declare(strict_types=1);

namespace Kuria\Cache\Driver;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;

/**
 * Driver interface
 *
 * All methods should throw DriverExceptionInterface on failure.
 *
 * @see DriverExceptionInterface
 */
interface DriverInterface
{
    /**
     * See if an entry exists
     */
    function exists(string $key): bool;

    /**
     * Read an entry
     *
     * Returns NULL for nonexistent entries.
     */
    function read(string $key);

    /**
     * Write an entry
     */
    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void;

    /**
     * Delete an entry
     */
    function delete(string $key): void;

    /**
     * Delete all entries
     */
    function clear(): void;
}
