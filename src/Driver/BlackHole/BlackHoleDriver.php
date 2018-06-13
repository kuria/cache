<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\BlackHole;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Feature\FilterableInterface;

/**
 * Black hole driver
 *
 * Does not read or write anything.
 */
class BlackHoleDriver implements DriverInterface, FilterableInterface
{
    function exists(string $key): bool
    {
        return false;
    }

    function read(string $key, &$exists = null)
    {
        $exists = false;

        return null;
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
    }

    function delete(string $key): void
    {
    }

    function clear(): void
    {
    }

    function filter(string $prefix): void
    {
    }

    function listKeys(string $prefix = ''): iterable
    {
        return [];
    }
}
