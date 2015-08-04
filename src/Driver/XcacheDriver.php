<?php

namespace Kuria\Cache\Driver;

/**
 * Xcache driver
 *
 * @author ShiraNai7 <shira.cz>
 */
class XcacheDriver implements DriverInterface, FilterableInterface
{
    public function exists($key)
    {
        return xcache_isset($key);
    }

    public function fetch($key)
    {
        $value = xcache_get($key);

        if (null === $value) {
            // xcache returns NULL on failure
            // NULLs are stored serialized (as a string) so this is OK
            return false;
        } elseif (is_string($value)) {
            // if the data is a string, it must have been serialized - see store()
            return @unserialize($value);
        } else {
            // scalar value
            return $value;
        }
    }

    public function store($key, $value, $overwrite, $ttl = 0)
    {
        // non-scalar values must be serialized
        // (xcache does not support objects and just crashes)

        // strings are always serialized to resolve ambiguity
        // (plain vs serialized string)

        if (!is_scalar($value) || is_string($value)) {
            $value = serialize($value);
        }

        // the following check is not 100%, but there is no xcache_add()
        if ($overwrite || !xcache_isset($key)) {
            return xcache_set($key, $value, $ttl);
        } else {
            return false;
        }
    }

    public function expunge($key)
    {
        return xcache_unset($key);
    }

    public function purge()
    {
        xcache_unset_by_prefix(''); // should return bool, but doesn't

        return true;
    }

    public function filter($prefix)
    {
        xcache_unset_by_prefix($prefix); // should return bool, but doesn't

        return true;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        $success = false;
        
        if ($offset > 0) {
            $newValue = xcache_inc($key, $offset);
        } else {
            $newValue = xcache_dec($key, abs($offset));
        }

        if (is_int($newValue)) {
            $success = true;
        }

        return $newValue;
    }
}
