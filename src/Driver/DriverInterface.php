<?php

namespace Kuria\Cache\Driver;

/**
 * Cache driver interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface DriverInterface
{
    /**
     * See if a key exists
     *
     * @param string $key
     * @return bool
     */
    public function exists($key);

    /**
     * Fetch a value
     *
     * @param string $key
     * @return mixed false on failure
     */
    public function fetch($key);

    /**
     * Store a value
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $overwrite
     * @param int    $ttl
     * @return bool
     */
    public function store($key, $value, $overwrite, $ttl = 0);

    /**
     * Remove a key
     *
     * @param string $key
     * @return bool
     */
    public function expunge($key);

    /**
     * Remove all keys
     *
     * @return bool
     */
    public function purge();

    /**
     * Modify existing integer value
     *
     * @param string $key
     * @param int    $offset   non-zero offset, either positive or negative
     * @param bool   &$success variable to put success state into
     * @return int|bool the new value, current value or false (depending on success)
     */
    public function modifyInteger($key, $offset, &$success = null);
}
