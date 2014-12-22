<?php

namespace Kuria\Cache;

/**
 * Local cache interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface LocalCacheInterface
{
    /**
     * See if an entry exists
     *
     * @param string $name entry name
     * @return bool
     */
    public function has($name);

    /**
     * Get an entry
     *
     * @param string $name    entry name
     * @param array  $options array of options (used by callbacks)
     * @return mixed false on failure
     */
    public function get($name, array $options = array());

    /**
     * Create an entry
     * Does not overwrite existing entry.
     *
     * @param string $name    entry name
     * @param mixed  $data    value to store
     * @param int    $ttl     time to live in seconds (0 = forever)
     * @param array  $options array of options (used by callbacks)
     * @return bool
     */
    public function add($name, $data, $ttl = 0, array $options = array());

    /**
     * Set an entry
     * Overwrites if the entry already exists.
     *
     * @param string $name    entry name
     * @param mixed  $data    value to store
     * @param int    $ttl     time to live in seconds (0 = forever)
     * @param array  $options array of options (used by callbacks)
     * @return bool
     */
    public function set($name, $data, $ttl = 0, array $options = array());

    /**
     * Increment an integer entry
     * The entry must exist and be of integer type.
     *
     * @param string $name     entry name
     * @param int    $step     how much to increment by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false if the entry is not valid
     */
    public function increment($name, $step = 1, &$success = null);

    /**
     * Decrement an integer entry
     * The entry must exist and be of integer type.
     *
     * @param string $name     entry name
     * @param int    $step     how much to decrement by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false if the entry is not valid
     */
    public function decrement($name, $step = 1, &$success = null);

    /**
     * Remove an entry
     *
     * @param string $name entry name
     * @return bool
     */
    public function remove($name);

    /**
     * Clear all entries
     *
     * @return bool
     */
    public function clear();
}
