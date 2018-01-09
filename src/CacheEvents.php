<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;

/**
 * @see Cache
 */
abstract class CacheEvents
{
    /**
     * Emitted when a value has been read
     *
     * @param CacheEvent $event
     */
    const READ = 'read';

    /**
     * Emitted before a value is written
     *
     * @param CacheEvent $event
     */
    const WRITE = 'write';

    /**
     * Emitted when an internal driver exception occurs
     *
     * @param DriverExceptionInterface $e
     */
    const DRIVER_EXCEPTION = 'driver.exception';
}
