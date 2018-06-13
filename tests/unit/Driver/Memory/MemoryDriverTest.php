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
        $this->callNowAndAfter(
            function () {
                $this->driver->write('key', 'value');
                $this->driver->write('expires', 'value', 10);

                $this->assertTrue($this->driver->exists('key'));
                $this->assertTrue($this->driver->exists('expires'));
                $this->assertFalse($this->driver->exists('nonexistent'));
            },
            function () {
                $this->assertTrue($this->driver->exists('key'));
                $this->assertFalse($this->driver->exists('expires'));
                $this->assertFalse($this->driver->exists('nonexistent'));
            }
        );
    }

    function testRead()
    {
        $this->callNowAndAfter(
            function () {
                $this->driver->write('key', 'value');
                $this->driver->write('expires', 123, 60);

                $this->assertSame('value', $this->driver->read('key', $exists));
                $this->assertTrue($exists);
                unset($exists);

                $this->assertSame(123, $this->driver->read('expires', $exists));
                $this->assertTrue($exists);
                unset($exists);

                $this->assertNull($this->driver->read('nonexistent', $exists));
                $this->assertFalse($exists);
                unset($exists);
            },
            function () {
                $this->assertSame('value', $this->driver->read('key', $exists));
                $this->assertTrue($exists);
                unset($exists);

                $this->assertNull($this->driver->read('expires', $exists));
                $this->assertFalse($exists);
                unset($exists);

                $this->assertNull($this->driver->read('nonexistent', $exists));
                $this->assertFalse($exists);
                unset($exists);
            }
        );
    }

    /**
     * @dataProvider provideTtl
     */
    function testWrite(?int $ttl, int $offset, bool $shouldExpire)
    {
        $this->callNowAndAfter(
            function () use ($ttl) {
                $this->driver->write('key', 'value');
                $this->driver->write('with_ttl', 123, $ttl);

                $this->assertSame('value', $this->driver->read('key'));
                $this->assertSame(123, $this->driver->read('with_ttl'));
            },
            function () use ($shouldExpire) {
                $this->assertSame('value', $this->driver->read('key'));

                if ($shouldExpire) {
                    $this->assertNull($this->driver->read('with_ttl'));
                    $this->assertFalse($this->driver->exists('with_ttl'));
                } else {
                    $this->assertSame(123, $this->driver->read('with_ttl'));
                }
            },
            $offset
        );
    }

    function provideTtl(): array
    {
        return [
            // ttl, offset, shouldExpire
            [60, 59, false],
            [60, 60, true],
            [1, 0, false],
            [1, 1, true],
            [30, 150, true],
            [0, 60, false],
            [null, 10, false],
            [-1, 5, false],
        ];
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
        $this->callNowAndAfter(
            function () {
                $this->driver->write('key', 'value', 10);
            },
            function () {
                $this->expectException(DriverExceptionInterface::class);
                $this->expectExceptionMessage('Failed to delete entry');

                $this->driver->delete('key');
            }
        );
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
        $this->callNowAndAfter(
            function () {
                $this->driver->write('foo', 'bar');
                $this->driver->write('baz', 'qux', 60);
                $this->driver->write('mlem', 'boop');

                $this->assertCount(3, $this->driver);

                $this->driver->cleanup();

                $this->assertCount(3, $this->driver);
                $this->assertSameIterable(['foo', 'baz', 'mlem'], $this->driver->listKeys());
            },
            function () {
                $this->driver->cleanup();

                $this->assertCount(2, $this->driver);
                $this->assertSameIterable(['foo', 'mlem'], $this->driver->listKeys());
            }
        );
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
        $this->callNowAndAfter(
            function () {
                $this->driver->write('foo', 'bar');
                $this->driver->write('baz', 'qux', 30);
                $this->driver->write('mlem', 'boop');

                $this->assertSameIterable(['foo', 'baz', 'mlem'], $this->driver->listKeys());
                $this->assertSameIterable(['foo'], $this->driver->listKeys('f'));
                $this->assertSameIterable(['baz'], $this->driver->listKeys('b'));
            },
            function () {
                $this->assertSameIterable(['foo', 'mlem'], $this->driver->listKeys());
                $this->assertSameIterable(['foo'], $this->driver->listKeys('f'));
                $this->assertSameIterable([], $this->driver->listKeys('b'));
            }
        );
    }

    function testCount()
    {
        $this->callNowAndAfter(
            function () {
                $this->assertCount(0, $this->driver);
                $this->driver->write('foo', 'bar');
                $this->assertCount(1, $this->driver);
                $this->driver->write('baz', 'qux', 20);
                $this->assertCount(2, $this->driver);
                $this->driver->write('mlem', 'boop');
                $this->assertCount(3, $this->driver);
            },
            function () {
                $this->assertCount(3, $this->driver); // expired entries should still be in the cache

                $this->driver->clear();

                $this->assertCount(0, $this->driver);
            }
        );
    }

    private function callNowAndAfter(callable $now, callable $after, int $offset = 60): void
    {
        $time = 1000;
        $timeMockNs = ['Kuria\\Cache\\Driver\\Helper', __NAMESPACE__];

        TimeMachine::setTime($timeMockNs, $time, $now);
        TimeMachine::setTime($timeMockNs, $time + $offset, $after);
    }
}
