<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;

/**
 * No cache implementation
 *
 * Does not cache and almost always fails.
 * For testing purposes.
 *
 * @author ShiraNai7 <shira.cz>
 */
class NoCache extends Cache
{
    protected function exists($key)
    {
        return false;
    }

    protected function fetch($key)
    {
        return false;
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        return false;
    }

    protected function expunge($key)
    {
        return false;
    }

    protected function purge($prefix)
    {
        return true;
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        $success = false;

        return false;
    }
}
