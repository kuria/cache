<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\Apcu\ApcuDriver;
use Kuria\Cache\Driver\DriverInterface;

/**
 * @group integration
 * @requires extension apcu
 */
class ApcuCacheTest extends CacheTest
{
    protected function createDriver(): DriverInterface
    {
        return new ApcuDriver();
    }
}
