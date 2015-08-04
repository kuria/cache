<?php

namespace Kuria\Cache\Driver;

/**
 * Filterable cache driver interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface FilterableInterface
{
    /**
     * Remove keys that begin with the given prefix
     *
     * @param string $prefix
     * @return bool
     */
    public function filter($prefix);
}
