<?php

namespace Kuria\Cache\Driver;

/**
 * @requires extension memcache
 */
class MemcacheDriverTest extends DriverTest
{
    /** @var \Memcache */
    private $memcache;

    public function provideDriverFactories()
    {
        $that = $this;

        return array(
            array(function () use ($that) {
                $memcache = $that->getMemcache();

                if (false === $memcache) {
                    $that->markTestSkipped(sprintf(
                        'The memcache server %s:%d is not responding',
                        $_ENV['MEMCACHE_TEST_HOST'],
                        $_ENV['MEMCACHE_TEST_PORT']
                    ));
                } else {
                    return new MemcacheDriver($memcache);
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
    private function createMemcache()
    {
        $host = $_ENV['MEMCACHE_TEST_HOST'];
        $port = $_ENV['MEMCACHE_TEST_PORT'];

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
