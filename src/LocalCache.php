<?php

namespace Kuria\Cache;

/**
 * Local cache
 *
 * This is esentially a wrapper class for the Cache.
 * It represents a single category within that cache.
 *
 * @author ShiraNai7 <shira.cz>
 */
class LocalCache implements LocalCacheInterface
{
    /** @var Cache */
    protected $cache;
    /** @var string */
    protected $category;

    /*
     * @param Cache  $cache
     * @param string $category
     */
    public function __construct(Cache $cache, $category)
    {
        $this->cache = $cache;
        $this->category = $category;
    }

    public function has($name)
    {
        return $this->cache->has($this->category, $name);
    }

    public function get($name, array $options = array())
    {
        return $this->cache->get($this->category, $name, $options);
    }

    public function add($name, $data, $ttl = 0, array $options = array())
    {
        return $this->cache->add($this->category, $name, $data, $ttl, $options);
    }

    public function set($name, $data, $ttl = 0, array $options = array())
    {
        return $this->cache->set($this->category, $name, $data, $ttl, $options);
    }

    public function increment($name, $step = 1, &$success = null)
    {
        return $this->cache->increment($this->category, $name, $step, $success);
    }

    public function decrement($name, $step = 1, &$success = null)
    {
        return $this->cache->decrement($this->category, $name, $step, $success);
    }

    public function remove($name)
    {
        return $this->cache->remove($this->category, $name);
    }

    public function clear()
    {
        return $this->cache->clear($this->category);
    }
}
