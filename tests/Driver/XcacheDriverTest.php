<?php

namespace Kuria\Cache\Driver;

/**
 * @requires extension xcache
 */
class XcacheDriverTest extends DriverTest
{
    public function provideDriverFactories()
    {
        return array(
            array(function () {
                $driver = new XcacheDriver();

                // make sure the cache is empty
                $driver->purge();

                return $driver;
            }),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        if ('cli' === PHP_SAPI && !ini_get('xcache.test')) {
            $this->markTestSkipped('XCache is not enabled for CLI environment (php.ini - xcache.test)');
        }
    }
}
