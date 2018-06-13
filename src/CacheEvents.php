<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;

/**
 * @see Cache
 */
abstract class CacheEvents
{
    /**
     * Emitted when an entry has been read
     *
     * @param string $key
     * @param mixed $value
     */
    const HIT = 'hit';

    /**
     * Emitted when an entry has not been found
     *
     * @param string $key
     */
    const MISS = 'miss';

    /**
     * Emitted when an entry is about to be written
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param bool $overwrite
     */
    const WRITE = 'write';

    /**
     * Emitted when an internal driver exception occurs
     *
     * @param DriverExceptionInterface $e
     */
    const DRIVER_EXCEPTION = 'driver.exception';
}
