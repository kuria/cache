<?php

namespace Kuria\Cache\Driver;

/**
 * @requires extension wincache
 */
class WinCacheDriverTest extends DriverTest
{
    public function provideDriverFactories()
    {
        return array(
            array(function () {
                $driver = new WinCacheDriver();

                // make sure the cache is empty
                $driver->purge();
                
                return $driver;
            }),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        if ('cli' === PHP_SAPI && !ini_get('wincache.enablecli')) {
            $this->markTestSkipped('WinCache is not enabled for the CLI environment (see php.ini - wincache.enablecli)');
        }
    }
}
