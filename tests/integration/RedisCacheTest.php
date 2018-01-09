<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Redis\RedisDriver;

/**
 * @group integration
 * @requires extension redis
 */
class RedisCacheTest extends CacheTest
{
    protected function createDriver(): DriverInterface
    {
        $host = getenv('REDIS_TEST_HOST');
        $port = (int) getenv('REDIS_TEST_PORT');

        if (empty($host) || empty($port)) {
            $this->markTestSkipped('Test Redis server is not configured');
        }

        $redis = new \Redis();

        if (!$redis->connect($host, $port)) {
            $this->fail(sprintf('Could not connect to test Redis server at %s:%d', $host, $port));
        }

        return new RedisDriver($redis);
    }
}
