<?php

namespace Kuria\Cache\Driver;

/**
 * Memcache driver
 *
 * @author ShiraNai7 <shira.cz>
 */
class MemcacheDriver implements DriverInterface, MultipleFetchInterface
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

    public function exists($key)
    {
        // there is no "exists"-like method in memcache
        // so we just attempt to store "false" for the given key
        // which will fail for existing keys and act as
        // "no value" if it is actually read
        return false === $this->memcache->add($key, false, 0, 1);
    }

    public function fetch($key)
    {
        return $this->memcache->get($key);
    }

    public function fetchMultiple(array $keys)
    {
        $values = $this->memcache->get($keys);

        if (false === $values) {
            $values = array(); // @codeCoverageIgnore
        } // @codeCoverageIgnore

        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                $values[$key] = false;
            }
        }

        return $values;
    }

    public function store($key, $value, $overwrite, $ttl = 0)
    {
        // always store as an UNIX timestamp
        // (see description of the expire parameter in the PHP manunal)
        $expire = $ttl > 0 ? time() + $ttl : 0;

        if ($overwrite) {
            return $this->memcache->set($key, $value, 0, $expire);
        } else {
            return $this->memcache->add($key, $value, 0, $expire);
        }
    }

    public function expunge($key)
    {
        return $this->memcache->delete($key);
    }

    public function purge()
    {
        return $this->memcache->flush();
    }

    public function modifyInteger($key, $offset, &$success = null)
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
