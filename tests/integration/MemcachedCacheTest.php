<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Memcached\MemcachedDriver;

/**
 * @group integration
 * @requires extension memcached
 */
class MemcachedCacheTest extends CacheTest
{
    protected function createDriver(): DriverInterface
    {
        $host = getenv('MEMCACHED_TEST_HOST');
        $port = (int) getenv('MEMCACHED_TEST_PORT');

        if (empty($host) || empty($port)) {
            $this->markTestSkipped('Test Memcached server is not configured');
        }

        $memcached = new \Memcached();
        $memcached->addServer($host, $port);
        
        if (!is_array($memcached->getStats())) {
            $this->fail(sprintf('Could not connect to test Memcached server at %s:%d', $host, $port));
        }

        return new MemcachedDriver($memcached);
    }
}
