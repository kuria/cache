<?php

namespace Kuria\Cache\Driver;

abstract class DriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Provide list of test instance creators (callbacks)
     *
     * @return callback[]
     */
    abstract public function provideDriverFactories();

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testApi($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        $this->assertFalse($driver->exists('test.foo'), 'exists() returns false for non-existent entry');
        $this->assertTrue($driver->store('test.foo', 123, true), 'store() returns true on success');
        $this->assertSame(123, $driver->fetch('test.foo'), 'fetch() returns the previously stored data');
        $this->assertFalse($driver->store('test.foo', 321, false), 'non-overwriting store() returns false for already existing entry');
        $this->assertSame(123, $driver->fetch('test.foo'), 'non-overwriting store() does not overwrite existing entry');
        $this->assertTrue($driver->store('test.bar', 'hello', false), 'non-overwriting store() returns true on success');
        $this->assertSame('hello', $driver->fetch('test.bar'), 'fetch() returns the previously stored data');
        $this->assertTrue($driver->store('test.bar2', 'hello2', false));

        $this->assertSame(124, $driver->modifyInteger('test.foo', 1, $incrementSuccess), 'modifyInteger(+1) returns the new value');
        $this->assertTrue($incrementSuccess, 'modifyInteger(+1) sets the success variable correctly');
        $this->assertSame(124, $driver->fetch('test.foo'), 'modified values can be read with fetch()');

        $this->assertSame(123, $driver->modifyInteger('test.foo', -1, $decrementSuccess), 'modifyInteger(-1) returns the new value');
        $this->assertTrue($decrementSuccess, 'modifyInteger(-1) sets the success variable correctly');
        $this->assertSame(123, $driver->fetch('test.foo'), 'modified values can be read with fetch()');

        $this->assertTrue($driver->expunge('test.foo'), 'expunge() returns true on success');
        $this->assertFalse($driver->expunge('test.baz'), 'expunge() returns false on failure');

        $this->assertFalse($driver->fetch('test.nonexistent'), 'fetch() returns false on failure');
    }

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testModifyingNonintegerValues($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        $driver->store('notaninteger', 'hello', false);

        // when modifying non-integer or nonexistent values, the results
        // are undefined, but the driver should return a non-NULL value
        // and set the success variable

        $success = null;
        $this->assertNotNull($driver->modifyInteger('notaninteger', 1, $success));
        $this->assertNotNull($success);

        $success = null;
        $this->assertNotNull($driver->modifyInteger('notaninteger', -1, $success));
        $this->assertNotNull($success);

        $success = null;
        $this->assertNotNull($driver->modifyInteger('nonexistent', 1, $success));
        $this->assertNotNull($success);

        $success = null;
        $this->assertNotNull($driver->modifyInteger('nonexistent2', -1, $success));
        $this->assertNotNull($success);
    }

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testTypeStorage($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        // null
        $driver->store('test.null', null, false);

        $this->assertSame(null, $driver->fetch('test.null'));

        // boolean
        $boolean1 = true;
        $boolean2 = false;

        $driver->store('test.bool1', $boolean1, false);
        $driver->store('test.bool2', $boolean2, false);
        $this->assertSame($boolean1, $driver->fetch('test.bool1'));
        $this->assertSame($boolean2, $driver->fetch('test.bool2'));

        // integer
        $integer1 = 123;
        $integer2 = -123;

        $driver->store('test.int1', $integer1, false);
        $driver->store('test.int2', $integer2, false);
        $this->assertSame($integer1, $driver->fetch('test.int1'));
        $this->assertSame($integer2, $driver->fetch('test.int2'));

        // float
        $float1 = 1.23;
        $float2 = -1.23;

        $driver->store('test.float1', $float1, false);
        $driver->store('test.float2', $float2, false);
        $this->assertSame($float1, $driver->fetch('test.float1'));
        $this->assertSame($float2, $driver->fetch('test.float2'));

        // string
        $string = "foo ěščřžýáíé\nbar";

        $driver->store('test.string', $string, false);
        $this->assertSame($string, $driver->fetch('test.string'));

        // array
        $array = array(
            'data' => 'foo',
            'foo' => 'bar',
        );

        $driver->store('test.array', $array, false);
        $this->assertSame($array, $driver->fetch('test.array'));

        // object
        $object = new \stdClass();
        $object->foo = 'bar';

        $this->assertTrue($driver->store('test.object', $object, false));
        $this->assertEquals($object, $driver->fetch('test.object'));
    }

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testPurge($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        $driver->store('foo.example', 1, false);
        $driver->store('bar.example', 2, false);

        $this->assertTrue($driver->purge());

        $this->assertFalse($driver->exists('foo.example'), 'purge() removes all keys');
        $this->assertFalse($driver->exists('bar.example'), 'purge() removes all keys');
    }

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testFilter($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        if ($driver instanceof FilterableInterface) {
            $driver->store('foo.lorem', 1, false);
            $driver->store('foo.ipsum', 2, false);
            $driver->store('foodolor', 3, false);
            $driver->store('bar.sit', 4, false);
            $driver->store('bar.amet', 5, false);

            $this->assertTrue($driver->filter('foo.ips'));

            $this->assertTrue($driver->exists('foo.lorem'));
            $this->assertFalse($driver->exists('foo.ipsum'));
            $this->assertTrue($driver->exists('foodolor'));
            $this->assertTrue($driver->exists('bar.sit'));
            $this->assertTrue($driver->exists('bar.amet'));

            $this->assertTrue($driver->filter('foo.'));

            $this->assertFalse($driver->exists('foo.lorem'));
            $this->assertFalse($driver->exists('foo.ipsum'));
            $this->assertTrue($driver->exists('foodolor'));
            $this->assertTrue($driver->exists('bar.sit'));
            $this->assertTrue($driver->exists('bar.amet'));

            $this->assertTrue($driver->filter('foo'));

            $this->assertFalse($driver->exists('foo.lorem'));
            $this->assertFalse($driver->exists('foo.ipsum'));
            $this->assertFalse($driver->exists('foodolor'));
            $this->assertTrue($driver->exists('bar.sit'));
            $this->assertTrue($driver->exists('bar.amet'));

            $this->assertTrue($driver->filter(''));

            $this->assertFalse($driver->exists('foo.lorem'));
            $this->assertFalse($driver->exists('foo.ipsum'));
            $this->assertFalse($driver->exists('foodolor'));
            $this->assertFalse($driver->exists('bar.sit'));
            $this->assertFalse($driver->exists('bar.amet'));
        }
    }

    /**
     * @dataProvider provideDriverFactories
     * @param callable $driverFactory
     */
    public function testFetchMultiple($driverFactory)
    {
        $driver = $driverFactory();
        /* @var $driver DriverInterface */

        if ($driver instanceof MultipleFetchInterface) {
            $this->assertSame(array(), $driver->fetchMultiple(array()));
            $this->assertSame(array('foo' => false), $driver->fetchMultiple(array('foo')));

            $driver->store('foo', 1, false);
            $driver->store('bar', 2, false);

            $values = $driver->fetchMultiple(array('foo', 'bar', 'baz'));

            $this->assertInternalType('array', $values);
            $this->assertArrayHasKey('foo', $values);
            $this->assertArrayHasKey('bar', $values);
            $this->assertArrayHasKey('baz', $values);

            $this->assertSame(1, $values['foo']);
            $this->assertSame(2, $values['bar']);
            $this->assertFalse($values['baz']);
        }
    }
}
