<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Apcu;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Feature\MultiDeleteInterface;
use Kuria\Cache\Driver\Feature\MultiReadInterface;
use Kuria\Cache\Driver\Feature\MultiWriteInterface;
use Kuria\Cache\Driver\Helper\TtlHelper;
use Kuria\Cache\Helper\IterableHelper;

class ApcuDriver implements DriverInterface, MultiReadInterface, MultiWriteInterface, MultiDeleteInterface, FilterableInterface
{
    function exists(string $key): bool
    {
        return apcu_exists($key);
    }

    function read(string $key, &$exists = null)
    {
        try {
            $value = apcu_fetch($key, $exists);
        } catch (\Throwable $e) {
            throw new DriverException('An exception was thrown when reading the entry', 0, $e);
        }

        if (!$exists) {
            return null;
        }

        return $value;
    }

    function readMultiple(iterable $keys): iterable
    {
        try {
            $values = apcu_fetch(IterableHelper::toArray($keys), $success);
        } catch (\Throwable $e) {
            throw new DriverException('An exception was thrown when reading multiple entries', 0, $e);
        }

        if (!$success) {
            throw new DriverException('Failed to fetch multiple entries');
        }

        return $values;
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
        if ($overwrite) {
            $success = apcu_store($key, $value, TtlHelper::normalize($ttl));
        } else {
            $success = apcu_add($key, $value, TtlHelper::normalize($ttl));
        }

        if (!$success) {
            throw new DriverException('Failed to write entry');
        }
    }

    function writeMultiple(iterable $values, ?int $ttl = null, bool $overwrite = false): void
    {
        if ($overwrite) {
            $failedKeys = apcu_store(IterableHelper::toArray($values), null, TtlHelper::normalize($ttl));
        } else {
            $failedKeys = apcu_add(IterableHelper::toArray($values), null, TtlHelper::normalize($ttl));
        }

        if ($failedKeys) {
            throw new DriverException(sprintf('Failed to write entries: %s', implode(', ', $failedKeys)));
        }
    }

    function delete(string $key): void
    {
        if (!apcu_delete($key)) {
            throw new DriverException('Failed to delete entry');
        }
    }

    function deleteMultiple(iterable $keys): void
    {
        $failedKeys = apcu_delete(IterableHelper::toArray($keys));

        if ($failedKeys) {
            throw new DriverException(sprintf('Failed to delete entries: %s', implode(', ', $failedKeys)));
        }
    }

    function clear(): void
    {
        apcu_clear_cache();
    }

    function filter(string $prefix): void
    {
        if (!apcu_delete($this->createApcuIterator($prefix))) {
            throw new DriverException('Failed to filter entries');
        }
    }

    function listKeys(string $prefix = ''): iterable
    {
        foreach ($this->createApcuIterator($prefix) as $entry) {
            yield $entry['key'];
        }
    }

    protected function createApcuIterator(string $prefix): \APCuIterator
    {
        if ($prefix !== '') {
            $search = sprintf('{%s}A', preg_quote($prefix));
        } else {
            $search = null;
        }

        return new \APCuIterator($search, APC_ITER_KEY);
    }
}
