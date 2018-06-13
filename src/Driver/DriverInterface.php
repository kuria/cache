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
     * - returns NULL for nonexistent entries
     * - the $exists parameter will be set to TRUE or FALSE, depending on whether the entry was found or not
     * - in case of an exception during reading, the $exists parameter will not be changed
     */
    function read(string $key, &$exists = null);

    /**
     * Write an entry
     *
     * - $ttl specifies expiration as a number of seconds from now
     * - $ttl = NULL or $ttl <= 0 means no expiration
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
