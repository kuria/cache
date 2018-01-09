<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Exception\UnsupportedOperationException;

interface CacheInterface extends \IteratorAggregate
{
    /**
     * See whether the given entry exists
     */
    function has(string $key): bool;

    /**
     * Get a value
     *
     * Returns NULL if the value is not found.
     */
    function get(string $key);

    /**
     * Get multiple values
     *
     * - returns an associative array with the retrieved values
     * - nonexistent keys will have NULL value
     */
    function getMultiple(iterable $keys): array;

    /**
     * List keys, optionally filtered by a prefix
     *
     * The order of the returned keys is undefined.
     *
     * @see CacheInterface::isFilterable()
     * @throws UnsupportedOperationException if the driver doesn't support this operation
     */
    function listKeys(string $prefix = ''): iterable;

    /**
     * Get iterator for keys and values, optionally filtered by a prefix
     *
     * The order of the returned key-value pairs is undefined.
     *
     * @see CacheInterface::isFilterable()
     * @throws UnsupportedOperationException if the driver doesn't support this operation
     */
    function getIterator(string $prefix = ''): \Traversable;

    /**
     * Add a new entry
     *
     * - if the key already exists, it will NOT be overwritten and FALSE will be returned
     * - TTL is time-to-live in seconds
     */
    function add(string $key, $value, ?int $ttl = null): bool;

    /**
     * Add multiple entries
     *
     * @see CacheInterface::add()
     */
    function addMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * Set an entry
     *
     * - if the key already exists, it will be overwritten.
     * - TTL is time-to-live in seconds
     */
    function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Set multiple entries
     *
     * @see CacheInterface::set()
     */
    function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * Try to get a cached value. If it doesn't exist, invoke the given callback,
     * store its result in the cache and return it.
     *
     * - uses add() if $overwrite is FALSE, set() otherwise
     * - if the callback returns NULL it will not be stored in the cache
     *
     * @see CacheInterface::add()
     * @see CacheInterface::set()
     */
    function cached(string $key, ?int $ttl, callable $callback, bool $overwrite = false);

    /**
     * Delete an entry
     *
     * Returns FALSE if removing an existing entry fails.
     */
    function delete(string $key): bool;

    /**
     * Delete multiple entries
     */
    function deleteMultiple(iterable $keys): bool;

    /**
     * Delete all entries with the given prefix
     *
     * @see CacheInterface::isFilterable()
     * @throws UnsupportedOperationException if the driver doesn't support this operation
     */
    function filter(string $prefix): bool;

    /**
     * See if filtering is supported
     *
     * @see CacheInterface::filter()
     * @see CacheInterface::listKeys()
     */
    function isFilterable(): bool;

    /**
     * Delete all entries
     *
     * Warning: If the driver doesn't support filtering, all entries will be deleted (regardless of configured prefix).
     *
     * @see CacheInterface::isFilterable()
     */
    function clear(): bool;

    /**
     * Trigger cleanup procedures
     *
     * @see CacheInterface::supportsCleanup()
     * @throws UnsupportedOperationException if the driver doesn't support this operation
     */
    function cleanup(): bool;

    /**
     * See if cleanup is supported
     *
     * @see CacheInterface::cleanup()
     */
    function supportsCleanup(): bool;

    /**
     * Get key prefix
     */
    function getPrefix(): string;

    /**
     * Set key prefix
     */
    function setPrefix(string $prefix): void;
}
