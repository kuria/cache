<?php

namespace Kuria\Cache\Driver;

/**
 * Multiple fetch cache driver interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface MultipleFetchInterface
{
    /**
     * Get values for multiple keys
     *
     * Returns an array with all of the keys. The keys which could not
     * be found will be FALSE.
     *
     * @param string[] $keys the key
     * @return array
     */
    public function fetchMultiple(array $keys);
}
