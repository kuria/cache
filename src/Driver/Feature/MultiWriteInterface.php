<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface MultiWriteInterface
{
    /**
     * Write multiple entries
     */
    function writeMultiple(iterable $values, ?int $ttl = null, bool $overwrite = false): void;
}
