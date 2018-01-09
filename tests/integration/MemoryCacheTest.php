<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Memory\MemoryDriver;

/**
 * @group integration
 */
class MemoryCacheTest extends CacheTest
{
    protected function createDriver(): DriverInterface
    {
        return new MemoryDriver();
    }

    protected function driverUsesSerialization(): bool
    {
        return false;
    }
}
