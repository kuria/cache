<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Memory;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\TimeMachine;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class MemoryDriverTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var MemoryDriver */
    private $driver;

    protected function setUp()
    {
        $this->driver = new MemoryDriver();
    }

    function testExists()
    {
        $this->driver->write('key', 'value');
        $this->driver->write('expired_key', 'value', -1);

        $this->assertTrue($this->driver->exists('key'));
        $this->assertFalse($this->driver->exists('expired_key'));
        $this->assertFalse($this->driver->exists('nonexistent'));
    }

    function testRead()
    {
        $this->driver->write('key', 'value');
        $this->driver->write('expired_key', 'value', -1);

        $this->assertSame('value', $this->driver->read('key'));
        $this->assertNull($this->driver->read('expired_key'));
        $this->assertNull($this->driver->read('nonexistent'));
    }

    function testWrite()
    {
        TimeMachine::freezeTime([__NAMESPACE__], function () {
            $this->driver->write('foo', 'bar');
            $this->driver->write('baz', 'qux', 10);

            $this->assertSame('bar', $this->driver->read('foo'));
            $this->assertSame('qux', $this->driver->read('baz'));
        });
    }

    function testDisabledOverwrite()
    {
        $this->driver->write('foo', 'bar');

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('A valid entry for this key already exists');

        $this->driver->write('foo', 'overwritten');
    }

    function testEnabledOverwrite()
    {
        $this->driver->write('foo', 'bar');
        $this->driver->write('foo', 'overwritten', null, true);

        $this->assertSame('overwritten', $this->driver->read('foo'));
    }

    function testDelete()
    {
        $this->driver->write('foo', 'bar');

        $this->assertTrue($this->driver->exists('foo'));
        $this->assertSame('bar', $this->driver->read('foo'));

        $this->driver->delete('foo');

        $this->assertFalse($this->driver->exists('foo'));
        $this->assertNull($this->driver->read('foo'));
    }

    function testDeletingNonexistentEntry()
    {
        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testDeletingExpiredEntry()
    {
        $this->driver->write('key', 'value', -1);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testClear()
    {
        $this->driver->write('foo', 'bar');
        $this->driver->write('baz', 'qux');

        $this->assertTrue($this->driver->exists('foo'));
        $this->assertTrue($this->driver->exists('baz'));

        $this->driver->clear();

        $this->assertFalse($this->driver->exists('foo'));
        $this->assertFalse($this->driver->exists('baz'));
    }

    function testCleanup()
    {
        $this->driver->write('foo', 'bar');
        $this->driver->write('baz', 'qux', -1);
        $this->driver->write('mlem', 'boop');

        $this->assertCount(3, $this->driver);

        $this->driver->cleanup();

        $this->assertCount(2, $this->driver);
        $this->assertSameIterable(['foo', 'mlem'], $this->driver->listKeys());
    }

    function testFilter()
    {
        $this->driver->write('foo_a', 'bar');
        $this->driver->write('foo_b', 'baz');
        $this->driver->write('qux', 'mlem');

        $this->assertTrue($this->driver->exists('foo_a'));
        $this->assertTrue($this->driver->exists('foo_b'));
        $this->assertTrue($this->driver->exists('qux'));

        $this->driver->filter('foo_');

        $this->assertFalse($this->driver->exists('foo_a'));
        $this->assertFalse($this->driver->exists('foo_b'));
        $this->assertTrue($this->driver->exists('qux'));
    }

    function testListKeys()
    {
        $this->driver->write('foo', 'bar');
        $this->driver->write('baz', 'qux', -1);
        $this->driver->write('mlem', 'boop');

        $this->assertSameIterable(['foo', 'mlem'], $this->driver->listKeys());
        $this->assertSameIterable(['foo'], $this->driver->listKeys('f'));
    }

    function testCount()
    {
        $this->assertCount(0, $this->driver);
        $this->driver->write('foo', 'bar');
        $this->assertCount(1, $this->driver);
        $this->driver->write('baz', 'qux', -1);
        $this->assertCount(2, $this->driver);
        $this->driver->write('mlem', 'boop');
        $this->assertCount(3, $this->driver);
        $this->driver->clear();
        $this->assertCount(0, $this->driver);
    }
}
