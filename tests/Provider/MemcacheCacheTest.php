<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\CacheTest;

/**
 * @requires extension memcache
 */
class MemcacheCacheTest extends CacheTest
{
    /** @var \Memcache */
    private $memcache;

    public function provideTestInstanceCreators()
    {
        $that = $this;

        return array(
            array(function () use ($that) {
                $memcache = $that->getMemcache();

                if (false === $memcache) {
                    $that->markTestSkipped(sprintf(
                        'The memcache server %s:%d is not available / running',
                        MEMCACHE_TEST_HOST,
                        MEMCACHE_TEST_PORT
                    ));
                } else {
                    return new MemcacheCache($memcache);
                }
            }),
        );
    }

    /**
     * Get test Memcache instance
     *
     * @return \Memcache|bool false if not available
     */
    public function getMemcache()
    {
        if (null === $this->memcache) {
            $this->memcache = $this->createMemcache();
        }

        return $this->memcache;
    }

    /**
     * Create a test Memcache instance
     *
     * @return \Memcache|bool
     */
    protected function createMemcache()
    {
        $host = MEMCACHE_TEST_HOST;
        $port = MEMCACHE_TEST_PORT;

        $memcache = new \Memcache();
        $memcache->addServer($host, $port);

        $status = @$memcache->getExtendedStats();

        if (
            is_array($status)
            && isset($status["{$host}:{$port}"])
            && false !== $status["{$host}:{$port}"]
        ) {
            // the memcache server is up and running
            return $memcache;
        } else {
            // the memcache server is not running
            return false;
        }
    }
}
