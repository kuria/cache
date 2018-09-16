<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Driver\Feature\CleanupInterface;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Feature\MultiDeleteInterface;
use Kuria\Cache\Driver\Feature\MultiReadInterface;
use Kuria\Cache\Driver\Feature\MultiWriteInterface;
use Kuria\Cache\Exception\UnsupportedOperationException;
use Kuria\Event\Observable;
use Kuria\Iterable\IterableHelper;

class Cache extends Observable implements CacheInterface
{
    use CachePrefixTrait;

    /**
     * @var DriverInterface|CleanupInterface|FilterableInterface|MultiReadInterface|MultiWriteInterface|MultiDeleteInterface|mixed
     */
    private $driver;

    function __construct(DriverInterface $driver, string $prefix = '')
    {
        $this->driver = $driver;
        $this->setPrefix($prefix);
    }

    function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Get a cache wrapper that applies the given prefix to keys for all operations
     */
    function getNamespace(string $prefix): NamespacedCache
    {
        return new NamespacedCache($this, $prefix);
    }

    function has(string $key): bool
    {
        try {
            return $this->driver->exists($this->applyPrefix($key));
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    function get(string $key, &$exists = null)
    {
        try {
            $value = $this->driver->read($this->applyPrefix($key), $exists);
        } catch (DriverExceptionInterface $e) {
            $exists = false;
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return null;
        }

        if ($exists) {
            $this->emit(CacheEvents::HIT, $key, $value);
        } else {
            $this->emit(CacheEvents::MISS, $key);
        }

        return $value;
    }

    function getMultiple(iterable $keys, &$failedKeys = null): array
    {
        $failedKeys = [];

        return $this->driver instanceof MultiReadInterface
            ? $this->getMultipleNative($keys, $failedKeys)
            : $this->getMultipleEmulated($keys, $failedKeys);
    }

    private function getMultipleNative(iterable $keys, array &$failedKeys): array
    {
        $keys = IterableHelper::toArray($keys);

        if (empty($keys)) {
            return [];
        }

        $values = array_fill_keys($keys, null);
        $failedKeyMap = array_fill_keys($keys, true);

        // fetch values
        try {
            foreach ($this->driver->readMultiple($this->applyPrefixToValues($keys)) as $prefixedKey => $value) {
                $key = $this->stripPrefix($prefixedKey);
                unset($failedKeyMap[$key]);

                $this->emit(CacheEvents::HIT, $key, $value);

                $values[$key] = $value;
            }
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);
        }

        // handle failed keys
        if ($failedKeyMap) {
            $failedKeys = array_keys($failedKeyMap);

            foreach ($failedKeys as $failedKey) {
                $this->emit(CacheEvents::MISS, $failedKey);
            }
        }

        return $values;
    }

    private function getMultipleEmulated(iterable $keys, array &$failedKeys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $exists);

            if (!$exists) {
                $failedKeys[] = $key;
            }
        }

        return $values;
    }

    function listKeys(string $prefix = ''): iterable
    {
        if (!$this->isFilterable()) {
            throw new UnsupportedOperationException(sprintf('Cannot list keys - the "%s" driver is not filterable', get_class($this->driver)));
        }

        try {
            foreach ($this->driver->listKeys($this->applyPrefix($prefix)) as $prefixedKey) {
                yield $this->stripPrefix($prefixedKey);
            }
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);
        }
    }

    function add(string $key, $value, ?int $ttl = null): bool
    {
        return $this->write($key, $value, $ttl, false);
    }

    function addMultiple(iterable $values, ?int $ttl = null): bool
    {
        return $this->driver instanceof MultiWriteInterface
            ? $this->writeMultipleNative($values, $ttl, false)
            : $this->writeMultipleEmulated($values, $ttl, false);
    }

    function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->write($key, $value, $ttl, true);
    }

    function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        return $this->driver instanceof MultiWriteInterface
            ? $this->writeMultipleNative($values, $ttl, true)
            : $this->writeMultipleEmulated($values, $ttl, true);
    }

    function cached(string $key, ?int $ttl, callable $callback, bool $overwrite = false)
    {
        $value = $this->get($key, $exists);

        if (!$exists) {
            $value = $callback();

            if ($overwrite) {
                $this->set($key, $value, $ttl);
            } else {
                $this->add($key, $value, $ttl);
            }
        }

        return $value;
    }

    private function write(string $key, $value, ?int $ttl, bool $overwrite): bool
    {
        $this->emit(CacheEvents::WRITE, $key, $value, $ttl, $overwrite);

        try {
            $this->driver->write($this->applyPrefix($key), $value, $ttl, $overwrite);

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    private function writeMultipleNative(iterable $values, ?int $ttl, bool $overwrite): bool
    {
        try {
            $this->driver->writeMultiple(
                $this->processMultipleValuesToWrite($values, $ttl, $overwrite),
                $ttl,
                $overwrite
            );

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    private function processMultipleValuesToWrite(iterable $values, ?int $ttl, bool $overwrite): iterable
    {
        foreach ($values as $key => $value) {
            $this->emit(CacheEvents::WRITE, $key, $value, $ttl, $overwrite);

            yield $this->applyPrefix($key) => $value;
        }
    }

    private function writeMultipleEmulated(iterable $values, ?int $ttl, bool $overwrite): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->write($key, $value, $ttl, $overwrite)) {
                $success = false;
            }
        }

        return $success;
    }

    function delete(string $key): bool
    {
        try {
            $this->driver->delete($this->applyPrefix($key));

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    function deleteMultiple(iterable $keys): bool
    {
        return $this->driver instanceof MultiDeleteInterface
            ? $this->deleteMultipleNative($keys)
            : $this->deleteMultipleEmulated($keys);
    }

    private function deleteMultipleNative(iterable $keys): bool
    {
        try {
            $this->driver->deleteMultiple($this->applyPrefixToValues($keys));

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    private function deleteMultipleEmulated(iterable $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    function filter(string $prefix): bool
    {
        if (!$this->isFilterable()) {
            throw new UnsupportedOperationException(sprintf('Cannot filter - the "%s" driver is not filterable', get_class($this->driver)));
        }

        try {
            $this->driver->filter($this->applyPrefix($prefix));

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    function isFilterable(): bool
    {
        return $this->driver instanceof FilterableInterface;
    }

    function clear(): bool
    {
        if ($this->isFilterable() && $this->prefix !== '') {
            return $this->filter('');
        }

        try {
            $this->driver->clear();

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    function cleanup(): bool
    {
        if (!$this->supportsCleanup()) {
            throw new UnsupportedOperationException(sprintf('The "%s" driver does not support cleanup', get_class($this->driver)));
        }

        try {
            $this->driver->cleanup();

            return true;
        } catch (DriverExceptionInterface $e) {
            $this->emit(CacheEvents::DRIVER_EXCEPTION, $e);

            return false;
        }
    }

    function supportsCleanup(): bool
    {
        return $this->driver instanceof CleanupInterface;
    }

    function getIterator(string $prefix = ''): \Traversable
    {
        foreach ($this->listKeys($prefix) as $key) {
            if (($value = $this->get($key)) !== null) {
                yield $key => $value;
            }
        }
    }
}
