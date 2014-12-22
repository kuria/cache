<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\CacheTest;

/**
 * @requires extension xcache
 */
class XcacheCacheTest extends CacheTest
{
    public function provideTestInstanceCreators()
    {
        return array(
            array(function () {
                $cache = new XcacheCache();

                // make sure the cache is empty
                $cache->clear();

                return $cache;
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
