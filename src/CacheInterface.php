<?php

namespace Kuria\Cache;

/**
 * Cache facade interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface CacheInterface
{
    /**
     * Set key prefix
     *
     * @param string $prefix
     * @throws \InvalidArgumentException if the prefix is not valid
     * @return static
     */
    public function setPrefix($prefix);

    /**
     * Get instance for a specific namespace
     *
     * @param string $prefix
     * @return CacheInterface
     */
    public function getNamespace($prefix);

    /**
     * See if a key exists
     *
     * @param string $key the key
     * @return bool
     */
    public function has($key);

    /**
     * Get a value for the given key
     *
     * @param string $key the key
     * @param array  $options array of options (used by events)
     * @return mixed false on failure
     */
    public function get($key, array $options = array());

    /**
     * Get values for multiple keys
     *
     * Returns an array with all of the keys. The keys which could not
     * be found will be FALSE.
     *
     * The keys will be fetched all at once or one by one, depending on
     * the underlying driver's implementation.
     *
     * @param string[] $keys the key
     * @param array    $options array of options (used by events)
     * @return array associative array with all of the keys
     */
    public function getMultiple(array $keys, array $options = array());

    /**
     * Get a value for the given key
     *
     * If the key is found, its value will be returned right away.
     *
     * If the key is not found, the callback will be called with the following
     * arguments:
     *
     *      1. &$ttl - reference to the TTL setting
     *         - it will be passed to add() after the callback finishes
     *         - defaults to 0
     *      2. &$options - reference to the options array
     *         - it will be passed to add() after the callback finishes
     *         - defaults to an empty array
     *
     * If the callback returns anything else than FALSE, that value will
     * be stored in the cache using add() and then returned.
     *
     * Using add() implies that if multiple threads happen to generate a value
     * at the same time, only one of the values will be stored.
     *
     * If the callback returns FALSE, it will not be stored and FALSE will
     * be returned.
     *
     * @param string   $key      the key to load or store
     * @param callable $callback the callback to invoke if the key is not found
     * @param array    $options  array of options (used by fetch events)
     * @return mixed false on failure
     */
    public function cached($key, $callback, array $options = array());

    /**
     * Create a new value
     * 
     * Does not overwrite if the key already exists.
     *
     * @param string $key the key
     * @param mixed  $value   value to store
     * @param int    $ttl     time to live in seconds (0 = forever)
     * @param array  $options array of options (used by events)
     * @return bool
     */
    public function add($key, $value, $ttl = 0, array $options = array());

    /**
     * Set a value
     * 
     * Overwrites if the key already exists.
     *
     * @param string $key the key
     * @param mixed  $value   value to store
     * @param int    $ttl     time to live in seconds (0 = forever)
     * @param array  $options array of options (used by events)
     * @return bool
     */
    public function set($key, $value, $ttl = 0, array $options = array());

    /**
     * Increment an integer value
     * 
     *  - the key must exist and the value should be an integer.
     *  - the result of incrementing non-integers is undefined and depends on the
     *    underlying driver implementation
     *
     * @param string $key the key
     * @param int    $step     how much to increment by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false on failure
     */
    public function increment($key, $step = 1, &$success = null);

    /**
     * Decrement an integer value
     * 
     *  - the key must exist and the value should be an integer.
     *  - the result of decrementing non-integers is undefined and depends on the
     *    underlying driver implementation
     *
     * @param string $key the key
     * @param int    $step     how much to decrement by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false on failure
     */
    public function decrement($key, $step = 1, &$success = null);

    /**
     * Remove a key
     *
     * @param string $key  the key
     * @return bool
     */
    public function remove($key);

    /**
     * Remove all keys
     *
     * This method will obey the current prefix configuration if the underlying
     * driver supports filtering. If not, the entire cache will be cleared
     * regardless of the prefix.
     *
     * @return bool
     */
    public function clear();

    /**
     * Remove keys that begin with the given prefix
     *
     * This method will return FALSE if the driver doesn't support this
     * operation or the operation fails.
     *
     * {@see Driver\FilterableInterface}
     *
     * @param string $prefix
     * @return bool
     */
    public function filter($prefix);

    /**
     * See if the underlying driver supports filtering
     *
     * @return bool
     */
    public function canFilter();
}
