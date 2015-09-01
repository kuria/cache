<?php

namespace Kuria\Cache\Driver;

/**
 * WinCache driver
 *
 * @author ShiraNai7 <shira.cz>
 */
class WinCacheDriver implements DriverInterface, FilterableInterface, MultipleFetchInterface
{
    public function exists($key)
    {
        return wincache_ucache_exists($key);
    }

    public function fetch($key)
    {
        return wincache_ucache_get($key);
    }
    
    public function fetchMultiple(array $keys)
    {
        $values = wincache_ucache_get($keys);

        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                $values[$key] = false;
            }
        }

        return $values;
    }

    public function store($key, $value, $overwrite, $ttl = 0)
    {
        if ($overwrite) {
            return wincache_ucache_set($key, $value, $ttl);
        } else {
            // emits a warning if the key already exists
            return @wincache_ucache_add($key, $value, $ttl);
        }
    }

    public function expunge($key)
    {
        return wincache_ucache_delete($key);
    }

    public function purge()
    {
        return wincache_ucache_clear();
    }

    public function filter($prefix)
    {
        $prefixLen = strlen($prefix);
        $info = wincache_ucache_info();

        $keysToDelete = array();
        foreach ($info['ucache_entries'] as $entry) {
            if (empty($entry['is_session']) && 0 === strncmp($entry['key_name'], $prefix, $prefixLen)) {
                $keysToDelete[] = $entry['key_name'];
            }
        }

        if ($keysToDelete) {
            wincache_ucache_delete($keysToDelete);
        }

        return true;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        return @wincache_ucache_inc($key, $offset, $success);
    }
}
