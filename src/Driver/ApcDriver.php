<?php

namespace Kuria\Cache\Driver;

/**
 * APC / APCu cache driver
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
        $success = true;
        $pattern = '/^' . preg_quote($prefix, '/') . '/';
        $apcIterator = new \APCIterator('user', $pattern, APC_ITER_KEY, 100, APC_LIST_ACTIVE);

        foreach ($apcIterator as $value) {
            if (!apc_delete($value['key'])) {
                $success = false; // @codeCoverageIgnore
            } // @codeCoverageIgnore
        }

        return $success;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        if ($offset > 0) {
            return apc_inc($key, $offset, $success);
        } else {
            return apc_dec($key, abs($offset), $success);
        }
    }
}
