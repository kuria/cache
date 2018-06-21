<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\CacheInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class CacheItemPool implements CacheItemPoolInterface
{
    private const RESERVED_KEY_CHARS = '{}()/\\@:';

    /** @var CacheInterface */
    private $cache;

    /** @var CacheItem[] key-indexed */
    private $deferred = [];

    /** @var int|null */
    private $autoCommitCount;

    function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    function __destruct()
    {
        // commit any pending deferred items
        $this->commit();
    }

    /**
     * Get the configured auto-commit count
     */
    function getAutoCommitCount(): ?int
    {
        return $this->autoCommitCount;
    }

    /**
     * Set a maximum number of deferred items in memory before a automatic commit
     *
     * Setting it to NULL disables this feature.
     *
     * @see CacheItemPool::saveDeferred()
     * @see CacheItemPool::commit()
     */
    function setAutoCommitCount(?int $autoCommitCount): void
    {
        $this->autoCommitCount = $autoCommitCount;
    }

    /**
     * @return CacheItem
     */
    function getItem($key): CacheItemInterface
    {
        $this->ensureValidKey($key);

        return $this->deferred[$key] ?? new CacheItem($key, $this->cache->get($key, $exists), $exists);
    }

    function getItems(array $keys = [])
    {
        $items = [];
        $keysToFetch = [];

        // gather existing deferred items
        foreach ($keys as $key) {
            $this->ensureValidKey($key);

            if (isset($this->deferred[$key])) {
                $items[$key] = $this->deferred[$key];
            } else {
                $items[$key] = null;
                $keysToFetch[] = $key;
            }
        }

        // fetch other items
        if ($keysToFetch) {
            foreach ($this->cache->getMultiple($keys, $failedKeys) as $key => $value) {
                $items[$key] = new CacheItem($key, $value, true);
            }

            foreach ($failedKeys as $key) {
                $items[$key] = new CacheItem($key, null, false);
            }
        }

        return $items;
    }

    function hasItem($key)
    {
        $this->ensureValidKey($key);

        return $this->cache->has($key);
    }

    function clear()
    {
        $this->deferred = [];

        return $this->cache->clear();
    }

    function deleteItem($key)
    {
        $this->ensureValidKey($key);

        unset($this->deferred[$key]);

        return $this->cache->delete($key);
    }

    function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->ensureValidKey($key);

            unset($this->deferred[$key]);
        }

        return $this->cache->deleteMultiple($keys);
    }

    /**
     * @param CacheItem $item
     */
    function save(CacheItemInterface $item)
    {
        return $this->cache->set($item->getKey(), $item->get(), $item->getTtl());
    }

    /**
     * @param CacheItem $item
     */
    function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[$item->getKey()] = $item;

        if ($this->autoCommitCount !== null && count($this->deferred) >= $this->autoCommitCount) {
            return $this->commit();
        }

        return true;
    }

    function commit()
    {
        $success = true;
        $items = $this->deferred;
        $this->deferred = [];

        /** @var CacheItem[][] $commonTtlGroups */
        $commonTtlGroups = [];

        // group items by common TTL value
        foreach ($items as $item) {
            $commonTtlGroups[$item->getTtl()][] = $item;
        }

        // store items
        foreach ($commonTtlGroups as $ttl => $items) {
            $values = [];

            foreach ($items as $item) {
                $values[$item->getKey()] = $item->get();
            }

            if (!$this->cache->setMultiple($values, $ttl === '' ? null : $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    private function ensureValidKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidKeyException(sprintf('The key must be a string, %s given', gettype($key)));
        }

        if (strcspn($key, self::RESERVED_KEY_CHARS) !== strlen($key)) {
            throw new InvalidKeyException(sprintf('The key must not contain "%s" (as mandated by PSR-6), got "%s"', self::RESERVED_KEY_CHARS, $key));
        }
    }
}
