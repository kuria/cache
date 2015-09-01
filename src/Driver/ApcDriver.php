<?php

namespace Kuria\Cache\Driver;

/**
 * APC / APCu driver
 *
 * @author ShiraNai7 <shira.cz>
 */
class ApcDriver implements DriverInterface, FilterableInterface, MultipleFetchInterface
{
    public function exists($key)
    {
        return apc_exists($key);
    }

    public function fetch($key)
    {
        return apc_fetch($key);
    }
    
    public function fetchMultiple(array $keys)
    {
        $values = apc_fetch($keys);

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
            return apc_store($key, $value, $ttl);
        } else {
            return apc_add($key, $value, $ttl);
        }
    }

    public function expunge($key)
    {
        return apc_delete($key);
    }

    public function purge()
    {
        return apc_clear_cache('user');
    }

    public function filter($prefix)
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '/';
        $apcIterator = new \APCIterator('user', $pattern, APC_ITER_KEY, 100, APC_LIST_ACTIVE);

        foreach ($apcIterator as $value) {
            apc_delete($value['key']);
        }

        return true;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        return apc_inc($key, $offset, $success);
    }
}
