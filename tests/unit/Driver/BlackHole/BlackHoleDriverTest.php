<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\BlackHole;

use PHPUnit\Framework\TestCase;

class BlackHoleDriverTest extends TestCase
{
    /** @var BlackHoleDriver */
    private $driver;

    protected function setUp()
    {
        $this->driver = new BlackHoleDriver();
    }

    function testShouldNotReadOrWrite()
    {
        $assertEmptyCache = function () {
            $this->assertFalse($this->driver->exists('foo'));
            $this->assertFalse($this->driver->exists('bar'));
            $this->assertFalse($this->driver->exists('baz'));

            $this->assertNull($this->driver->read('foo', $exists));
            $this->assertFalse($exists);
            unset($exists);

            $this->assertNull($this->driver->read('bar', $exists));
            $this->assertFalse($exists);
            unset($exists);

            $this->assertNull($this->driver->read('baz', $exists));
            $this->assertFalse($exists);
            unset($exists);

            $this->assertSame([], $this->driver->listKeys());
            $this->assertSame([], $this->driver->listKeys('b'));
        };

        $assertEmptyCache();

        $this->driver->write('foo', 'value');
        $this->driver->write('bar', 123, 60);
        $this->driver->write('baz', true, 120, true);

        $assertEmptyCache();

        $this->driver->delete('foo');
        $this->driver->delete('bar');
        $this->driver->delete('baz');

        $assertEmptyCache();

        $this->driver->clear();

        $assertEmptyCache();

        $this->driver->filter('f');

        $assertEmptyCache();
    }
}
