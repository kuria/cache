<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Helper;

use Kuria\Clock\Clock;

abstract class TtlHelper
{
    /**
     * Determine whether the given TTL value should cause an entry to expire
     *
     * NULL or <= 0 values mean no expiration.
     */
    static function shouldExpire(?int $ttl): bool
    {
        return $ttl !== null && $ttl > 0;
    }

    /**
     * Normalize a TTL value
     *
     * Returns a positive integer or 0 (no expiration).
     */
    static function normalize(?int $ttl): int
    {
        return static::shouldExpire($ttl) ? $ttl : 0;
    }

    /**
     * Convert a TTL value to expiration time
     *
     * Return an UNIX timestamp or 0 (no expiration).
     */
    static function toExpirationTime(?int $ttl): int
    {
        return static::shouldExpire($ttl) ? Clock::time() + $ttl : 0;
    }
}
