<?php

namespace Kuria\Cache\Driver;

/**
 * Memory driver
 *
 * Stores values in script's memory.
 *
 * @author ShiraNai7 <shira.cz>
 */
class MemoryDriver implements DriverInterface, FilterableInterface
{
    /** @var array */
    protected $registry = array();

    public function exists($key)
    {
        return array_key_exists($key, $this->registry);
    }

    public function fetch($key)
    {
        $entry = $this->load($key);
        if (false !== $entry) {
            return $entry['value'];
        }

        return false;
    }

    public function store($key, $value, $overwrite, $ttl = 0)
    {
        if ($overwrite || !$this->exists($key)) {
            $this->registry[$key] = array(
                'ttl' => $ttl,
                'created_at' => time(),
                'value' => $value,
            );

            return true;
        }

        return false;
    }

    public function expunge($key)
    {
        if ($this->exists($key)) {
            unset($this->registry[$key]);

            return true;
        } else {
            return false;
        }
    }

    public function purge()
    {
        $this->registry = array();

        return true;
    }

    public function filter($prefix)
    {
        $prefixLen = strlen($prefix);

        foreach (array_keys($this->registry) as $key) {
            if (0 === strncmp($key, $prefix, $prefixLen)) {
                unset($this->registry[$key]);
            }
        }

        return true;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        $entry = $this->load($key);

        if (false !== $entry && is_int($entry['value'])) {
            $entry['value'] += $offset;
            $this->registry[$key] = $entry;
            $success = true;

            return $entry['value'];
        } else {
            $success = false;
        }

        return false;
    }

    /**
     * Load entry
     *
     * @param string $key
     * @param bool   $checkFreshness
     * @return array|bool entry array or false on failure
     */
    protected function load($key, $checkFreshness = true)
    {
        if ($this->exists($key)) {
            $entry = $this->registry[$key];
            if (!$checkFreshness || $this->isFresh($entry)) {
                // ok
                return $entry;
            } else {
                // stale
                $this->expunge($key);
            }
        }

        return false;
    }

    /**
     * Check freshness of an entry
     *
     * @param array $entry
     * @return bool
     */
    protected function isFresh(array $entry)
    {
        if (0 === $entry['ttl'] || $entry['created_at'] + $entry['ttl'] > time()) {
            // fresh
            return true;
        } else {
            // stale
            return false;
        }
    }
}
