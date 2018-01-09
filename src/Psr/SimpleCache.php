<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\CacheInterface;

class SimpleCache implements \Psr\SimpleCache\CacheInterface
{
    /** @var CacheInterface */
    protected $cache;

    function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    function get($key, $default = null)
    {
        return $this->cache->get($key) ?? $default;
    }

    function set($key, $value, $ttl = null)
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = $this->convertDateIntervalToTtl($ttl);
        }

        return $this->cache->set($key, $value, $ttl);
    }

    function delete($key)
    {
        return $this->cache->delete($key);
    }

    function clear()
    {
        return $this->cache->clear();
    }

    function getMultiple($keys, $default = null)
    {
        $values = [];

        foreach ($this->cache->getMultiple($keys) as $key => $value) {
            $values[$key] = $value ?? $default;
        }

        return $values;
    }

    function setMultiple($values, $ttl = null)
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = $this->convertDateIntervalToTtl($ttl);
        }

        return $this->cache->setMultiple($values, $ttl);
    }

    function deleteMultiple($keys)
    {
        return $this->cache->deleteMultiple($keys);
    }

    function has($key)
    {
        return $this->cache->has($key);
    }

    protected function convertDateIntervalToTtl(\DateInterval $interval): int
    {
        if ($interval->invert) {
            return 0;
        }

        return $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
    }
}
