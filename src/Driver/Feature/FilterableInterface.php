<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface FilterableInterface
{
    /**
     * Delete all entries with the given prefix
     */
    function filter(string $prefix): void;

    /**
     * List all keys with the given prefix
     */
    function listKeys(string $prefix = ''): iterable;
}
