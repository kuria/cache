<?php

namespace Kuria\Cache;

use Kuria\Cache\Extension\CacheExtensionInterface;

/**
 * Cache interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface CacheInterface
{
    /** Event type - get */
    const EVENT_FETCH = 'kuria.cache.fetch';
    /** Event type - set */
    const EVENT_STORE = 'kuria.cache.store';

    /**
     * Get local cache for the given category
     *
     * @param string $category
     * @return LocalCacheInterface
     */
    public function getLocal($category);

    /**
     * Get entry identifier prefix
     *
     * @return string
     */
    public function getPrefix();

    /**
     * Set entry identifier prefix
     *
     * It must end (but not begin with) with "/" (a forward slash).
     * The prefix may be an empty string (to disable it).
     *
     * Valid prefix examples:
     *
     *      foo/
     *      foo_dev/
     *      foo/
     *      foo/bar/
     *
     * @param string $prefix
     * @throws \InvalidArgumentException if the prefix is not valid
     * @return static
     */
    public function setPrefix($prefix);

    /**
     * See if an entry exists
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @return bool
     */
    public function has($category, $entry);

    /**
     * Get an entry
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @param array  $options  array of options (used by callbacks)
     * @return mixed false on failure
     */
    public function get($category, $entry, array $options = array());

    /**
     * Create an entry
     * Does not overwrite existing entry.
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @param mixed  $data     value to store
     * @param int    $ttl      time to live in seconds (0 = forever)
     * @param array  $options  array of options (used by callbacks)
     * @return bool
     */
    public function add($category, $entry, $data, $ttl = 0, array $options = array());

    /**
     * Set an entry
     * Overwrites if the entry already exists.
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @param mixed  $data     value to store
     * @param int    $ttl      time to live in seconds (0 = forever)
     * @param array  $options  array of options (used by callbacks)
     * @return bool
     */
    public function set($category, $entry, $data, $ttl = 0, array $options = array());

    /**
     * Increment an integer entry
     * The entry must exist and be of integer type.
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @param int    $step     how much to increment by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false if the entry is not valid
     */
    public function increment($category, $entry, $step = 1, &$success = null);

    /**
     * Decrement an integer entry
     * The entry must exist and be of integer type.
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @param int    $step     how much to decrement by, must be >= 1
     * @param bool   &$success variable to put success state into
     * @throws \InvalidArgumentException if the step is invalid
     * @return int|bool the new value, current value (on failure) or false if the entry is not valid
     */
    public function decrement($category, $entry, $step = 1, &$success = null);

    /**
     * Remove an entry
     *
     * @param string $category category name
     * @param string $entry    entry name
     * @return bool
     */
    public function remove($category, $entry);

    /**
     * Clear all entries or only those in the given category
     *
     * @param string|null $category
     * @return bool
     */
    public function clear($category = null);

    /**
     * See if the implementation supports clearing only the given category
     *
     * @return bool
     */
    public function supportsClearingCategory();
}
