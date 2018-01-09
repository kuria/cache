<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface MultiReadInterface
{
    /**
     * Read entires for the given keys
     *
     * Nonexistent keys should be skipped.
     */
    function readMultiple(iterable $keys): iterable;
}
