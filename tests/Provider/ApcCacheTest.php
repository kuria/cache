<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\CacheTest;

/**
 * @requires extension apc
 */
class ApcCacheTest extends CacheTest
{
    public function provideTestInstanceCreators()
    {
        return array(
            array(function () {
                $cache = new ApcCache();

                // make sure the cache is empty
                $cache->clear();
                
                return $cache;
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
