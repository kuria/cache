<?php

namespace Kuria\Cache;

use Kuria\Event\ExternalObservable;
use Kuria\Event\Observable;

/**
 * Base cache class
 *
 * Notes on $prefix, $category and $name:
 *
 *  - all of those should contain alphanumeric characters only
 *    with "_" and "/" being the only allowed exceptions
 *
 *  - this is not enforced but should be respected to maintain
 *    compatibility with all cache implementations that might
 *    be relying on this fact
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class Cache extends ExternalObservable implements CacheInterface
{
    /** @var string */
    protected $prefix = '';
    /** @var CallbackStack|null */
    protected $callbacks;

    protected function handleNullObservable()
    {
        $this->observable = new Observable();
    }

    public function getLocal($category)
    {
        return new LocalCache($this, $category);
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix($prefix)
    {
        if ('' !== $prefix) {
            if ('/' !== substr($prefix, -1)) {
                throw new \InvalidArgumentException('Prefix must end with "/"');
            }
            if ('/' === $prefix[0]) {
                throw new \InvalidArgumentException('Prefix must not start with "/"');
            }
        }

        $this->prefix = $prefix;

        return $this;
    }

    public function has($category, $entry)
    {
        return $this->exists("{$this->prefix}{$category}/{$entry}");
    }

    public function get($category, $entry, array $options = array())
    {
        $data = $this->fetch("{$this->prefix}{$category}/{$entry}");

        if (null !== $this->observable) {
            $this->observable->notifyObservers(
                new CacheFetchEvent($category, $entry, $data, $options),
                $this
            );
        }

        return $data;
    }

    public function add($category, $entry, $data, $ttl = 0, array $options = array())
    {
        if (null !== $this->observable) {
            $this->observable->notifyObservers(
                new CacheStoreEvent($category, $entry, $data, $ttl, $options),
                $this
            );
        }

        return $this->store("{$this->prefix}{$category}/{$entry}", $data, false, $ttl);
    }

    public function set($category, $entry, $data, $ttl = 0, array $options = array())
    {
        if (null !== $this->observable) {
            $this->observable->notifyObservers(
                new CacheStoreEvent($category, $entry, $data, $ttl, $options),
                $this
            );
        }

        return $this->store("{$this->prefix}{$category}/{$entry}", $data, true, $ttl);
    }

    public function increment($category, $entry, $step = 1, &$success = null)
    {
        $step = (int) $step;
        if ($step < 1) {
            throw new \InvalidArgumentException('The step must be >= 1');
        }

        return $this->modifyInteger("{$this->prefix}{$category}/{$entry}", $step, $success);
    }

    public function decrement($category, $entry, $step = 1, &$success = null)
    {
        $step = (int) $step;
        if ($step < 1) {
            throw new \InvalidArgumentException('The step must be >= 1');
        }

        return $this->modifyInteger("{$this->prefix}{$category}/{$entry}", -$step, $success);
    }

    public function remove($category, $entry)
    {
        return $this->expunge("{$this->prefix}{$category}/{$entry}");
    }

    public function clear($category = null)
    {
        if (null === $category) {
            return $this->purge($this->prefix);
        } else {
            return $this->purge("{$this->prefix}{$category}/");
        }
    }

    public function supportsClearingCategory()
    {
        return true;
    }

    /**
     * See if data exists in the cache
     *
     * @param string $key
     * @return bool
     */
    abstract protected function exists($key);

    /**
     * Fetch data from the cache
     *
     * @param string $key
     * @return mixed false on failure
     */
    abstract protected function fetch($key);

    /**
     * Store data in the cache
     *
     * @param string $key
     * @param mixed  $data
     * @param bool   $overwrite
     * @param int    $ttl
     * @return bool
     */
    abstract protected function store($key, $data, $overwrite, $ttl);

    /**
     * Delete data from the cache
     *
     * @param string $key
     * @return bool
     */
    abstract protected function expunge($key);

    /**
     * Delete all data from the cache whose key matches the given prefix
     * The prefix may be empty. In that cache the whole cache is purged.
     *
     * @param string $prefix will end with "/", unless it is empty
     * @return bool
     */
    abstract protected function purge($prefix);

    /**
     * Modifify integer value in the cache
     *
     * @param string $key
     * @param int    $offset   non-zero offset, either positive or negative
     * @param bool   &$success variable to put success state into
     * @return int the new value, or current value (on failure) or false if the entry is not valid
     */
    abstract protected function modifyInteger($key, $offset, &$success = null);
}
