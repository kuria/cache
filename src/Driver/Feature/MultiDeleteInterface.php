<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface MultiDeleteInterface
{
    /**
     * Delete multiple entries
     */
    function deleteMultiple(iterable $keys): void;
}
