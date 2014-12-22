<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;

/**
 * XCache implementation
 *
 * @author ShiraNai7 <shira.cz>
 */
class XcacheCache extends Cache
{
    protected function exists($key)
    {
        return xcache_isset($key);
    }

    protected function fetch($key)
    {
        $data = xcache_get($key);

        if (null === $data) {
            // xcache returns NULL on failure
            // NULLs are stored serialized (as a string) so this is OK
            return false;
        } elseif (is_string($data)) {
            // if the data is a string, it must be serialized - see store()
            return @unserialize($data);
        } else {
            // scalar value
            return $data;
        }
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        // we must serialize non-scalar values
        // (xcache does not support objects and just crashes)

        // note: nulls are not considered scalar by is_scalar()
        // this is actually useful in this case - see fetch()

        // strings are always serialized to resolve ambiguity
        // (plain vs serialized string)

        if (!is_scalar($data) || is_string($data)) {
            $data = serialize($data);
        }

        // the following check is not 100%, but there is no xcache_add()
        if ($overwrite || !xcache_isset($key)) {
            return xcache_set($key, $data, $ttl);
        } else {
            return false;
        }
    }

    protected function expunge($key)
    {
        return xcache_unset($key);
    }

    protected function purge($prefix)
    {
        return xcache_unset_by_prefix($prefix);
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        $success = true; // sadly, success / failure is not reported by xcache

        if ($offset > 0) {
            return xcache_inc($key, $offset);
        } else {
            return xcache_dec($key, abs($offset));
        }
    }
}
