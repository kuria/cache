<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\CacheTest;

class MemoryCacheTest extends CacheTest
{
    public function provideTestInstanceCreators()
    {
        return array(
            array(function () {
                return new MemoryCache();
            }),
        );
    }
}
