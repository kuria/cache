<?php

namespace Kuria\Cache\Driver;

class MemoryDriverTest extends DriverTest
{
    public function provideDriverFactories()
    {
        return array(
            array(function () {
                return new MemoryDriver();
            }),
        );
    }
}
