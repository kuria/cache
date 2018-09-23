<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Cache;
use Kuria\Cache\Driver\DriverInterface;
use Kuria\DevMeta\Test;
use Kuria\Iterable\IterableHelper;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * @group integration
 */
abstract class CacheTest extends Test
{
    /** @var Cache */
    protected $cache;

    protected function setUp()
    {
        $this->cache = new Cache($this->createDriver());
        $this->cache->clear();
    }

    abstract protected function createDriver(): DriverInterface;

    protected function driverUsesSerialization(): bool
    {
        return true;
    }

    function testShouldPerformBasicOperations()
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('bar'));

        $this->assertNull($this->cache->get('foo'));
        $this->assertNull($this->cache->get('bar'));

        $this->assertTrue($this->cache->set('foo', 'foo_value'));
        $this->assertTrue($this->cache->set('bar', 'bar_value'));

        $this->assertSame('foo_value', $this->cache->get('foo'));
        $this->assertSame('bar_value', $this->cache->get('bar'));

        $this->cache->set('foo', 'new_foo_value');
        $this->cache->add('bar', 'new_bar_value');

        $this->assertSame('new_foo_value', $this->cache->get('foo'));
        $this->assertSame('bar_value', $this->cache->get('bar'));

        $this->assertTrue($this->cache->delete('foo'));
        $this->assertTrue($this->cache->delete('bar'));

        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('bar'));

        $this->assertNull($this->cache->get('foo'));
        $this->assertNull($this->cache->get('bar'));

        $this->assertFalse($this->cache->delete('foo'));
        $this->assertFalse($this->cache->delete('bar'));

        $this->assertSame('qux', $this->cache->cached('baz', null, function () { return 'qux'; }));
        $this->assertSame('qux', $this->cache->cached('baz', null, function () { return 'new_qux'; }));
    }

    function testAddShouldNotOverwiteExistingEntry()
    {
        $this->cache->set('foo', null);
        $this->assertNull($this->cache->get('foo'));
        $this->assertTrue($this->cache->has('foo'));

        $this->cache->add('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
        $this->assertNull($this->cache->get('foo'));
    }

    function testShouldClearCache()
    {
        $this->assertTrue($this->cache->set('foo', 'foo_value'));
        $this->assertTrue($this->cache->set('bar', 'bar_value'));

        $this->assertSame('foo_value', $this->cache->get('foo'));
        $this->assertSame('bar_value', $this->cache->get('bar'));

        $this->assertTrue($this->cache->clear());

        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('bar'));

        $this->assertNull($this->cache->get('foo'));
        $this->assertNull($this->cache->get('bar'));
    }

    function testShouldPerformMultiOperations()
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('bar'));
        $this->assertFalse($this->cache->has('baz'));

        $this->assertTrue(
            $this->cache->setMultiple([
                'foo' => 'foo_value',
                'bar' => 'bar_value',
                'baz' => 'baz_value',
            ])
        );

        $this->assertEqualIterable(
            [
                'foo' => 'foo_value',
                'bar' => 'bar_value',
                'baz' => 'baz_value',
            ],
            $this->cache->getMultiple(['foo', 'bar', 'baz'])
        );

        $this->assertTrue(
            $this->cache->setMultiple([
                'foo' => 'new_foo_value',
                'bar' => 'new_bar_value',
            ])
        );

        $this->assertEqualIterable(
            [
                'foo' => 'new_foo_value',
                'bar' => 'new_bar_value',
                'baz' => 'baz_value',
            ],
            $this->cache->getMultiple(['foo', 'bar', 'baz'])
        );

        $this->assertFalse(
            $this->cache->addMultiple([
                'baz' => 'new_baz_value',
                'qux' => 'qux_value',
            ])
        );

        $this->assertTrue(
            $this->cache->addMultiple([
                'quux' => 'quux_value',
                'corge' => 'corge_value',
            ])
        );

        $this->assertEqualIterable(
            [
                'foo' => 'new_foo_value',
                'bar' => 'new_bar_value',
                'baz' => 'baz_value',
                'qux' => 'qux_value',
                'quux' => 'quux_value',
                'corge' => 'corge_value',
            ],
            $this->cache->getMultiple(['foo', 'bar', 'baz', 'qux', 'quux', 'corge'])
        );

        $this->assertTrue($this->cache->deleteMultiple(['foo', 'bar']));

        $this->assertEqualIterable(
            [
                'foo' => null,
                'bar' => null,
                'baz' => 'baz_value',
                'qux' => 'qux_value',
                'quux' => 'quux_value',
                'corge' => 'corge_value',
            ],
            $this->cache->getMultiple(['foo', 'bar', 'baz', 'qux', 'quux', 'corge'])
        );

        $this->assertFalse($this->cache->deleteMultiple(['foo', 'bar', 'baz', 'qux', 'quux', 'corge']));

        $this->assertEqualIterable(
            [
                'foo' => null,
                'bar' => null,
                'baz' => null,
                'qux' => null,
                'quux' => null,
                'corge' => null,
            ],
            $this->cache->getMultiple(['foo', 'bar', 'baz', 'qux', 'quux', 'corge'])
        );
    }

    function testShouldFilterEntries()
    {
        if (!$this->cache->isFilterable()) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertSameIterable([], $this->cache->listKeys());

        $this->assertTrue(
            $this->cache->setMultiple([
                'foo' => 'foo_value',
                'bar' => 'bar_value',
                'baz' => 'baz_value',
            ])
        );

        $this->assertEqualIterable(['foo', 'bar', 'baz'], $this->cache->listKeys());

        $this->assertEqualIterable(
            [
                'foo' => 'foo_value',
                'bar' => 'bar_value',
                'baz' => 'baz_value',
            ],
            $this->cache
        );

        $this->assertEqualIterable(
            [
                'foo' => 'foo_value',
            ],
            $this->cache->getIterator('fo')
        );

        $this->assertTrue($this->cache->filter('ba'));
        $this->assertEqualIterable(['foo'], $this->cache->listKeys());
        $this->assertEqualIterable(['foo' => 'foo_value'], iterator_to_array($this->cache));
    }

    /**
     * @dataProvider provideValueTypes
     */
    function testShouldStoreSupportedValueTypes($value, ?Constraint $constraint = null)
    {
        $this->assertFalse($this->cache->has('key'));

        $this->cache->set('key', $value);

        $this->assertTrue($this->cache->has('key'));
        $this->assertThat($this->cache->get('key'), $constraint ?? $this->identicalTo($value));
    }

    function provideValueTypes()
    {
        $object = new \stdClass();
        $object->property = 'value';

        return [
            [true],
            [false],
            [null],
            [123],
            [-123],
            [0],
            [1.23],
            [-1.23],
            [NAN, $this->isNan()],
            [INF],
            [-INF],
            ['foo'],
            ["foo-\x00\x01\x02\x03-bar"],
            [[1, 2, 3]],
            [$object, $this->looselyIdenticalTo($object)],
        ];
    }

    function testGetShouldHandleSerializationErrors()
    {
        if (!$this->driverUsesSerialization()) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->cache->set('foo', new Undeserializable());

        $this->assertTrue($this->cache->has('foo'));
        $this->assertNull($this->cache->get('foo'));
    }

    function testGetMultipleShouldHandleSerializationErrors()
    {
        if (!$this->driverUsesSerialization()) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->cache->set('foo', 123);
        $this->cache->set('bar', new Undeserializable());

        $this->assertTrue($this->cache->has('foo'));
        $this->assertTrue($this->cache->has('bar'));
        $this->assertSame(123, $this->cache->get('foo'));
        $this->assertNull($this->cache->get('bar'));

        // invalid entries can either be NULL or the entire operation may fail
        // this depends on the driver implementation
        Assert::assertThat(
            IterableHelper::toArray($this->cache->getMultiple(['foo', 'bar'])),
            Assert::logicalOr(
                $this->identicalTo(['foo' => 123, 'bar' => null]),
                $this->identicalTo(['foo' => null, 'bar' => null])
            )
        );
    }
}
