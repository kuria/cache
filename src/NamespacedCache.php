<?php

namespace Kuria\Cache;

/**
 * Namespaced cache facade
 *
 * @author ShiraNai7 <shira.cz>
 */
class NamespacedCache implements CacheInterface
{
    /** @var CacheInterface */
    protected $wrappedCache;
    /** @var string */
    protected $prefix;

    /**
     * @param CacheInterface $wrappedCache
     * @param string         $prefix
     */
    public function __construct(CacheInterface $wrappedCache, $prefix)
    {
        $this->wrappedCache = $wrappedCache;
        $this->setPrefix($prefix);
    }

    public function setPrefix($prefix)
    {
        if ('' === $prefix || null === $prefix) {
            throw new \InvalidArgumentException('The prefix must not be empty');
        }
        
        $this->prefix = $prefix;
        
        return $this;
    }

    public function getNamespace($prefix)
    {
        // do not wrap self again, just combine the prefixes
        return new static($this->wrappedCache, $this->prefix . $prefix);
    }

    public function has($key)
    {
        return $this->wrappedCache->has($this->prefix . $key);
    }

    public function get($key, array $options = array())
    {
        return $this->wrappedCache->get($this->prefix . $key, $options);
    }

    public function getMultiple(array $keys, array $options = array())
    {
        // prefix keys first
        $prefixedKeys = array();
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefix . $key;
        }

        // fetch the values
        $values = $this->wrappedCache->getMultiple($prefixedKeys, $options);
        
        // output an array with unprefixed keys
        $output = array();
        for ($i = 0; isset($keys[$i]); ++$i) {
            $output[$keys[$i]] = $values[$prefixedKeys[$i]];
        }

        return $output;
    }

    public function cached($key, $callback, array $options = array())
    {
        return $this->wrappedCache->cached($this->prefix . $key, $callback, $options);
    }

    public function add($key, $value, $ttl = 0, array $options = array())
    {
        return $this->wrappedCache->add($this->prefix . $key, $value, $ttl, $options);
    }

    public function set($key, $value, $ttl = 0, array $options = array())
    {
        return $this->wrappedCache->set($this->prefix . $key, $value, $ttl, $options);
    }

    public function increment($key, $step = 1, &$success = null)
    {
        return $this->wrappedCache->increment($this->prefix . $key, $step, $success);
    }

    public function decrement($key, $step = 1, &$success = null)
    {
        return $this->wrappedCache->decrement($this->prefix . $key, $step, $success);
    }

    public function remove($key)
    {
        return $this->wrappedCache->remove($this->prefix . $key);
    }

    public function clear()
    {
        if ($this->wrappedCache->canFilter()) {
            return $this->wrappedCache->filter($this->prefix);
        } else {
            return $this->wrappedCache->clear();
        }
    }

    public function filter($prefix)
    {
        return $this->wrappedCache->filter($this->prefix . $prefix);
    }

    public function canFilter()
    {
        return $this->wrappedCache->canFilter();
    }
}
