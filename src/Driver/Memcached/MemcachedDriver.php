<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Memcached;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Driver\Feature\MultiDeleteInterface;
use Kuria\Cache\Driver\Feature\MultiReadInterface;
use Kuria\Cache\Driver\Feature\MultiWriteInterface;
use Kuria\Cache\Helper\IterableHelper;

/**
 * Memcached driver
 *
 * Does not implement FilterableInterface because getAllKeys() seems to be broken in recent builds.
 */
class MemcachedDriver implements DriverInterface, MultiReadInterface, MultiWriteInterface, MultiDeleteInterface
{
    /** @var \Memcached */
    private $memcached;

    function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    function exists(string $key): bool
    {
        try {
            $this->memcached->get($key);
        } catch (\Throwable $e) {
        }

        $resultCode = $this->memcached->getResultCode();

        return $resultCode === \Memcached::RES_SUCCESS || $resultCode === \Memcached::RES_PAYLOAD_FAILURE;
    }

    function read(string $key)
    {
        try {
            $value = $this->memcached->get($key);
        } catch (\Throwable $e) {
            throw new DriverException('An exception was thrown when reading the entry', 0, $e);
        }

        if ($this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
            return null;
        }

        return $value;
    }

    function readMultiple(iterable $keys): iterable
    {
        try {
            $values = $this->memcached->getMulti(IterableHelper::toArray($keys));
        } catch (\Throwable $e) {
            throw new DriverException('An exception was thrown when reading multiple entries', 0, $e);
        }

        if (!is_array($values)) {
            throw new DriverException('Failed to read multiple entries');
        }

        return $values;
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
        $expire = $ttl > 0 ? time() + $ttl : 0;

        if ($overwrite) {
            $success = $this->memcached->set($key, $value, $expire);
        } else {
            $success = $this->memcached->add($key, $value, $expire);
        }

        if (!$success) {
            throw new DriverException('Failed to write entry');
        }
    }

    function writeMultiple(iterable $values, ?int $ttl = null, bool $overwrite = false): void
    {
        $expire = $ttl > 0 ? time() + $ttl : 0;

        if ($overwrite) {
            $success = $this->memcached->setMulti(IterableHelper::toArray($values), $expire);
        } else {
            // there is no addMulti()

            $success = true;

            foreach ($values as $key => $value) {
                if (!$this->memcached->add($key, $value, $expire)) {
                    $success = false;
                }
            }
        }

        if (!$success) {
            throw new DriverException('Failed to write multiple entries');
        }
    }

    function delete(string $key): void
    {
        if (!$this->memcached->delete($key)) {
            throw new DriverException('Failed to delete entry');
        }
    }

    function deleteMultiple(iterable $keys): void
    {
        $failedKeyMap = array_filter(
            $this->memcached->deleteMulti(IterableHelper::toArray($keys)),
            function ($status) {return $status !== true; }
        );

        if ($failedKeyMap) {
            throw new DriverException(sprintf('Failed to delete entries: %s', implode(', ', array_keys($failedKeyMap))));
        }
    }

    function clear(): void
    {
        if (!$this->memcached->flush()) {
            throw new DriverException('Failed to flush entries');
        }
    }
}
