<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\PhpFileFormat;
use Kuria\Cache\Driver\Filesystem\Entry\FlockEntryFactory;
use Kuria\Cache\Driver\Filesystem\FilesystemDriver;

/**
 * @group integration
 */
class FilesystemCacheFlockTest extends FilesystemCacheDefaultTest
{
    protected function createDriver(): DriverInterface
    {
        return new FilesystemDriver(static::CACHE_PATH, new FlockEntryFactory(new PhpFileFormat()));
    }

    protected function driverUsesSerialization(): bool
    {
        return false;
    }
}
