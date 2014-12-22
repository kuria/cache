<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;

/**
 * APC cache implementation
 *
 * @author ShiraNai7 <shira.cz>
 */
class ApcCache extends Cache
{
    protected function exists($key)
    {
        return apc_exists($key);
    }

    protected function fetch($key)
    {
        return apc_fetch($key);
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        if ($overwrite) {
            return apc_store($key, $data, $ttl);
        } else {
            return apc_add($key, $data, $ttl);
        }
    }

    protected function expunge($key)
    {
        return apc_delete($key);
    }

    protected function purge($prefix)
    {
        if ('' === $prefix) {
            // entire cache
            return apc_clear_cache('user');
        } else {
            // the given prefix
            $search = sprintf('~^%s~', preg_quote($prefix, '~'));
            $apcIterator = new \APCIterator('user', $search, APC_ITER_KEY, 100, APC_LIST_ACTIVE);

            foreach ($apcIterator as $value) {
                if (!apc_delete($value['key'])) {
                    return false;
                }
            }

            return true;
        }
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        if ($offset > 0) {
            return apc_inc($key, $offset, $success);
        } else {
            return apc_dec($key, abs($offset), $success);
        }
    }
}
