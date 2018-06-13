<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface MultiWriteInterface
{
    /**
     * Write multiple entries
     *
     * - $ttl specifies expiration as a number of seconds from now
     * - $ttl = NULL or $ttl <= 0 means no expiration
     */
    function writeMultiple(iterable $values, ?int $ttl = null, bool $overwrite = false): void;
}
