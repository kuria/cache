<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;

/**
 * Memcache implementation
 *
 * @author ShiraNai7 <shira.cz>
 */
class MemcacheCache extends Cache
{
    /** @var \Memcache */
    protected $memcache;

    /**
     * @param \Memcache $memcache
     */
    public function __construct(\Memcache $memcache)
    {
        $this->memcache = $memcache;
    }

    protected function exists($key)
    {
        // there is no "exists"-like method in memcache
        // so we just attempt to store "false" for the given key
        // which will fail for existing keys and act as
        // "no value" if it is actually read
        return false === $this->memcache->add($key, false, 0, 1);
    }

    protected function fetch($key)
    {
        return $this->memcache->get($key);
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        $expire = $ttl > 0 ? time() + $ttl : 0;

        if ($overwrite) {
            if ($this->exists($key)) {
                return $this->memcache->replace($key, $data, 0, $expire);
            } else {
                return $this->memcache->set($key, $data, 0, $expire);
            }
        } else {
            return $this->memcache->add($key, $data, 0, $expire);
        }
    }

    protected function expunge($key)
    {
        return $this->memcache->delete($key);
    }

    protected function purge($prefix)
    {
        if ('' === $prefix) {
            // purge everything
            return $this->memcache->flush();
        } else {
            // there is no way to get all existing keys
            return false;
        }
    }

    public function supportsClearingCategory()
    {
        return false;
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        if ($offset > 0) {
            try {
                $result = $this->memcache->increment($key, $offset);
            } catch (\Exception $e) {
                $result = false;
            }
        } else {
            try {
                $result = $this->memcache->decrement($key, abs($offset));
            } catch (\Exception $e) {
                $result = false;
            }
        }

        $success = (false !== $result);

        return $result;
    }
}
