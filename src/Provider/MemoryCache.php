<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;

/**
 * Memory cache implementation
 *
 * Stores data in script's memory.
 *
 * @author ShiraNai7 <shira.cz>
 */
class MemoryCache extends Cache
{
    /** @var array */
    protected $registry = array();

    protected function exists($key)
    {
        return array_key_exists($key, $this->registry);
    }

    protected function fetch($key)
    {
        $entry = $this->load($key);
        if (false !== $entry) {
            return $entry['data'];
        }

        return false;
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        if ($overwrite || !$this->exists($key)) {
            $this->registry[$key] = array(
                'ttl' => $ttl,
                'created_at' => time(),
                'data' => $data,
            );

            return true;
        }

        return false;
    }

    protected function expunge($key)
    {
        if ($this->exists($key)) {
            unset($this->registry[$key]);

            return true;
        } else {
            return false;
        }
    }

    protected function purge($prefix)
    {
        if ('' === $prefix) {
            // entire cache
            $this->registry = array();
        } else {
            // the given prefix
            $search = sprintf('~^%s~', preg_quote($prefix, '~'));

            foreach (array_keys($this->registry) as $key) {
                if (preg_match($search, $key)) {
                    unset($this->registry[$key]);
                }
            }
        }

        return true;
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        $entry = $this->load($key);

        if (false !== $entry && is_int($entry['data'])) {
            $entry['data'] += $offset;
            $this->registry[$key] = $entry;
            $success = true;

            return $entry['data'];
        }

        return false;
    }

    /**
     * Load entry
     *
     * @param string $key
     * @param bool   $checkFreshness
     * @return array|bool entry array or false on failure
     */
    protected function load($key, $checkFreshness = true)
    {
        if ($this->exists($key)) {
            $entry = $this->registry[$key];
            if (!$checkFreshness || $this->isFresh($entry)) {
                // ok
                return $entry;
            } else {
                // stale
                $this->expunge($key);
            }
        }

        return false;
    }

    /**
     * Check freshness of an entry
     *
     * @param array $entry
     * @return bool
     */
    protected function isFresh($entry)
    {
        if (0 === $entry['ttl'] || $entry['created_at'] + $entry['ttl'] > time()) {
            // the entry is fresh
            return true;
        } else {
            // stale
            return false;
        }
    }
}
