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
        // reset last error
        if (PHP_MAJOR_VERSION >= 7) {
            error_clear_last();
        } else {
            set_error_handler('min', 0); // never called
            @trigger_error(null);
            restore_error_handler();
        }

        // there is no "exists()" method in memcache - ugly hack follows
        $result = @$this->memcache->increment($key, 0);

        // if the result is not FALSE, then the entry exists (and is an integer)
        // if the result is FALSE and no error has been raised, the entry does not exist
        // if the result is FALSE and an error has been raised, the entry exists (and is not an integer)
        return
            false !== $result
            || (
                ($error = error_get_last())
                && $error['file'] === __FILE__
                && $error['line'] === __LINE__ - 10
            )
        ;
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
        $result = @$this->memcache->increment($key, $offset);
        $success = (false !== $result);

        return $result;
    }
}
