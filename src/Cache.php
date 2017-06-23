<?php

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\FilterableInterface;
use Kuria\Cache\Driver\MultipleFetchInterface;
use Kuria\Event\EventEmitter;

/**
 * Cache facade
 *
 * @emits fetch(array $event) after the value been read from the driver
 * @emits store(array $event) before a value is stored using the driver
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
     * @param DriverInterface $driver
     * @param string|null     $prefix
     */
    public function __construct(DriverInterface $driver, $prefix = null)
    {
        $this->driver = $driver;
        
        if ($prefix !== null) {
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

        if ($this->hasListeners('fetch')) {
            $found = $value !== false;

            $this->emit('fetch', array(
                'key' => $key,
                'options' => &$options,
                'value' => &$value,
                'found' => $found,
            ));

            if ($found && $value === false) {
                // invalidated by extension
                $this->driver->expunge($key);
            }
        }

        return $value;
    }

    public function getMultiple(array $keys, array $options = array())
    {
        // prepare keys
        $keyMap = array();
        $processedKeys = array();
        $numDifferentKeys = 0;
        foreach ($keys as $key) {
            $processedKey = $this->processKey($key);

            $processedKeys[] = $processedKey;
            $keyMap[$processedKey] = $key;

            if ($processedKey !== $key) {
                ++$numDifferentKeys;
            }
        }

        // fetch the values
        if ($this->driver instanceof MultipleFetchInterface) {
            // using a multi-fetch driver
            $values = $this->driver->fetchMultiple($processedKeys);

            // remap the value array to use the original keys
            if ($numDifferentKeys > 0) {
                $remappedValues = array();

                foreach ($keyMap as $processedKey => $key) {
                    $remappedValues[$key] = $values[$processedKey];
                }

                $values = $remappedValues;
                $remappedValues = null;
            }
        } else {
            // one by one
            $values = array();

            // fetch and map in a single loop
            foreach ($keyMap as $processedKey => $key) {
                $values[$key] = $this->driver->fetch($processedKey);
            }
        }

        // emit an event for each key
        if ($this->hasListeners('fetch')) {
            foreach ($values as $key => &$value) {
                $found = $value !== false;
                $currentOptions = $options; // each emit should use its own copy

                $this->emit('fetch', array(
                    'key' => $key,
                    'options' => &$currentOptions,
                    'value' => &$value,
                    'found' => $found,
                ));

                if ($found && $value === false) {
                    // invalidated by extension
                    $this->driver->expunge($key);
                }
            }
        }

        return $values;
    }

    public function cached($key, $callback, array $options = array())
    {
        $value = $this->get($key, $options);

        if ($value === false) {
            $addTtl = 0;
            $addOptions = array();
            $value = call_user_func_array($callback, array(&$addTtl, &$addOptions));

            if ($value !== false) {
                $this->add($key, $value, $addTtl, $addOptions);
            }
        }

        return $value;
    }

    public function add($key, $value, $ttl = 0, array $options = array())
    {
        $key = $this->processKey($key);

        if ($this->hasListeners('store')) {
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

        if ($this->hasListeners('store')) {
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
        if ($this->prefix !== '' && $this->canFilter()) {
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
