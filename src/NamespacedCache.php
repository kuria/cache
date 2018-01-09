<?php declare(strict_types=1);

namespace Kuria\Cache;

/**
 * Cache wrapper that prefixes keys for all operations
 */
class NamespacedCache implements CacheInterface
{
    use CachePrefixTrait;

    /** @var CacheInterface */
    protected $wrappedCache;

    function __construct(CacheInterface $wrappedCache, string $prefix)
    {
        $this->wrappedCache = $wrappedCache;
        $this->setPrefix($prefix);
    }
    
    function getWrappedCache(): CacheInterface
    {
        return $this->wrappedCache;
    }

    function has(string $key): bool
    {
        return $this->wrappedCache->has($this->applyPrefix($key));
    }

    function get(string $key)
    {
        return $this->wrappedCache->get($this->applyPrefix($key));
    }

    function getMultiple(iterable $keys): array
    {
        $values = [];

        foreach ($this->wrappedCache->getMultiple($this->applyPrefixToValues($keys)) as $prefixedKey => $value) {
            $values[$this->stripPrefix($prefixedKey)] = $value;
        }

        return $values;
    }

    function listKeys(string $prefix = ''): iterable
    {
        foreach ($this->wrappedCache->listKeys($this->applyPrefix($prefix)) as $processedKey) {
            yield $this->stripPrefix($processedKey);
        }
    }

    function add(string $key, $value, ?int $ttl = null): bool
    {
        return $this->wrappedCache->add($this->applyPrefix($key), $value, $ttl);
    }

    function addMultiple(iterable $values, ?int $ttl = null): bool
    {
        return $this->wrappedCache->addMultiple($this->applyPrefixToKeys($values), $ttl);
    }

    function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->wrappedCache->set($this->applyPrefix($key), $value, $ttl);
    }

    function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        return $this->wrappedCache->setMultiple($this->applyPrefixToKeys($values), $ttl);
    }

    function cached(string $key, ?int $ttl, callable $callback, bool $overwrite = false)
    {
        return $this->wrappedCache->cached($this->applyPrefix($key), $ttl, $callback, $overwrite);
    }

    function delete(string $key): bool
    {
        return $this->wrappedCache->delete($this->applyPrefix($key));
    }

    function deleteMultiple(iterable $keys): bool
    {
        return $this->wrappedCache->deleteMultiple($this->applyPrefixToValues($keys));
    }

    function filter(string $prefix): bool
    {
        return $this->wrappedCache->filter($this->applyPrefix($prefix));
    }

    function isFilterable(): bool
    {
        return $this->wrappedCache->isFilterable();
    }

    function clear(): bool
    {
        if ($this->wrappedCache->isFilterable() && $this->prefix !== '') {
            return $this->wrappedCache->filter($this->prefix);
        }

        return $this->wrappedCache->clear();
    }

    function cleanup(): bool
    {
        return $this->wrappedCache->cleanup();
    }

    function supportsCleanup(): bool
    {
        return $this->wrappedCache->supportsCleanup();
    }

    function getIterator(string $prefix = ''): \Traversable
    {
        return $this->wrappedCache->getIterator($this->applyPrefix($prefix));
    }
}
