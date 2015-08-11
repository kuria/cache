<?php

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\FilterableInterface;
use Kuria\Cache\Driver\MultipleFetchInterface;
use Kuria\Event\EventEmitter;

/**
 * Cache facade
 *
 * @emits fetch(array $event)
 * @emits store(array $event)
 *
 * @author ShiraNai7 <shira.cz>
 */
class Cache extends EventEmitter implements CacheInterface
{
    /** @var string */
    protected $prefix = '';
    /** @var DriverInterface */
    protected $driver;

    /**
     *
     * @param DriverInterface $driver
     * @param string|null     $prefix
     */
    public function __construct(DriverInterface $driver, $prefix = null)
    {
        $this->driver = $driver;
        
        if (null !== $prefix) {
            $this->setPrefix($prefix);
        }
    }

    public function setPrefix($prefix)
    {
        $this->ensureValidPrefix($prefix);
        $this->prefix = $prefix;

        return $this;
    }

    public function getNamespace($prefix)
    {
        return new NamespacedCache($this, $prefix);
    }

    public function has($key)
    {
        return $this->driver->exists($this->processKey($key));
    }

    public function get($key, array $options = array())
    {
        $key = $this->processKey($key);
        $value = $this->driver->fetch($key);

        if (isset($this->listeners['fetch'])) {
            $this->emit('fetch', array(
                'key' => $key,
                'options' => &$options,
                'value' => &$value,
            ));
        }

        return $value;
    }

    public function getMultiple(array $keys, array $options = array())
    {
        // process the keys first
        $processedKeys = array();
        foreach ($keys as $key) {
            $processedKeys[] = $this->processKey($key);
        }

        // fetch the values
        if ($this->driver instanceof MultipleFetchInterface) {
            // using a multi-fetch driver
            $values = $this->driver->fetchMultiple($processedKeys);
        } else {
            // one by one
            $values = array();
            foreach ($processedKeys as $key) {
                $values[$key] = $this->driver->fetch($key);
            }
        }

        // emit an event for each key
        if (isset($this->listeners['fetch'])) {
            foreach ($values as $key => &$value) {
                $currentOptions = $options; // each emit should have its own copy

                $this->emit('fetch', array(
                    'key' => $key,
                    'options' => &$currentOptions,
                    'value' => &$value,
                ));
            }
        }

        return $values;
    }

    public function cached($key, $callback, array $options = array())
    {
        $value = $this->get($key, $options);

        if (false === $value) {
            $addTtl = 0;
            $addOptions = array();
            $value = call_user_func_array($callback, array(&$addTtl, &$addOptions));

            if (false !== $value) {
                $this->add($key, $value, $addTtl, $addOptions);
            }
        }

        return $value;
    }

    public function add($key, $value, $ttl = 0, array $options = array())
    {
        $key = $this->processKey($key);

        if (isset($this->listeners['store'])) {
            $this->emit('store', array(
                'key' => $key,
                'value' => &$value,
                'ttl' => &$ttl,
                'options' => &$options,
            ));
        }

        return $this->driver->store($key, $value, false, $ttl);
    }

    public function set($key, $value, $ttl = 0, array $options = array())
    {
        $key = $this->processKey($key);

        if (isset($this->listeners['store'])) {
            $this->emit('store', array(
                'key' => $key,
                'value' => &$value,
                'ttl' => &$ttl,
                'options' => &$options,
            ));
        }

        return $this->driver->store($key, $value, true, $ttl);
    }

    public function increment($key, $step = 1, &$success = null)
    {
        $step = (int) $step;

        if ($step < 1) {
            throw new \InvalidArgumentException('The step must be >= 1');
        }

        return $this->driver->modifyInteger($this->processKey($key), $step, $success);
    }

    public function decrement($key, $step = 1, &$success = null)
    {
        $step = (int) $step;

        if ($step < 1) {
            throw new \InvalidArgumentException('The step must be >= 1');
        }

        return $this->driver->modifyInteger($this->processKey($key), -$step, $success);
    }

    public function remove($key)
    {
        return $this->driver->expunge($this->processKey($key));
    }

    public function clear()
    {
        if ('' !== $this->prefix && $this->canFilter()) {
            return $this->driver->filter($this->prefix);
        } else {
            return $this->driver->purge();
        }
    }

    public function filter($prefix)
    {
        if ($this->canFilter()) {
            return $this->driver->filter($this->prefix . $prefix);
        } else {
            return false;
        }
    }

    public function canFilter()
    {
        return $this->driver instanceof FilterableInterface;
    }
    
    /**
     * Validate a prefix
     * 
     * @param string $prefix
     * @throws \InvalidArgumentException if the prefix is not valid
     */
    protected function ensureValidPrefix($prefix)
    {
        if (!preg_match('/^\w(?>\w+|\.(?!\.))*?$/', $prefix)) {
            throw new \InvalidArgumentException(sprintf(
                'The given prefix "%s" is invalid. Only alphanumeric characters, underscores and a dots are allowed. The prefix must begin with an alphanumeric character and must not contain consecutive dots.',
                $prefix
            ));
        }
    }

    /**
     * Process a key before it is passed to the driver's methods
     *
     * @param string $key
     * @throws \InvalidArgumentException if the key is not valid
     * @return string
     */
    protected function processKey($key)
    {
        if (!preg_match('/^\w(?>\w+|\.(?!\.|$))*?$/', $key)) {
            throw new \InvalidArgumentException(sprintf(
                'The given key "%s" is invalid. Only alphanumeric characters, underscores and a dots are allowed. The key must begin and end with an alphanumeric character and must not contain consecutive dots.',
                $key
            ));
        }

        return $this->prefix . $key;
    }
}
