<?php

namespace Kuria\Cache\Driver;

/**
 * @requires extension apc
 */
class ApcDriverTest extends DriverTest
{
    public function provideDriverFactories()
    {
        return array(
            array(function () {
                $driver = new ApcDriver();

                // make sure the cache is empty
                $driver->purge();
                
                return $driver;
            }),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        if ('cli' === PHP_SAPI && !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APC is not enabled for CLI environment (php.ini - apc.enable_cli)');
        }
    }
}
