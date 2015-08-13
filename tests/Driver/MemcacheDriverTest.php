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
                    $driver = new MemcacheDriver($memcache);

                    // make sure the cache is empty
                    $driver->purge();

                    return $driver;
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

    /**
     * @dataProvider provideDriverFactories
     */
    public function testExistsHack($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        $driver->store('foo', 123, true);
        $driver->store('bar', 'hello', true);
        $driver->store('baz', array(1, 2, 3), true);

        $this->assertTrue($driver->exists('foo'));
        $this->assertTrue($driver->exists('bar'));
        $this->assertTrue($driver->exists('baz'));
        $this->assertFalse($driver->exists('lorem'));

        $this->assertTrue($driver->store('lorem', 'test', false));
        $this->assertTrue($driver->exists('lorem'));

        $this->assertSame(123, $driver->fetch('foo'));
        $this->assertSame('hello', $driver->fetch('bar'));
        $this->assertSame(array(1, 2, 3), $driver->fetch('baz'));
        $this->assertSame('test', $driver->fetch('lorem'));
    }
}
