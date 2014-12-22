<?php

namespace Kuria\Cache;

abstract class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Provide list of test instance creators (callbacks)
     *
     * @return callback[]
     */
    abstract public function provideTestInstanceCreators();

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testPrefix($cacheProvider)
    {
        $cache = $cacheProvider();

        // default prefix should be empty
        $this->assertSame('', $cache->getPrefix());

        // set & get
        $this->assertSame('foo/', $cache->setPrefix('foo/')->getPrefix());
    }

    /**
     * @dataProvider provideTestInstanceCreators
     * @expectedException InvalidArgumentException
     */
    public function testPrefixNotEndingWithForwardSlashThrowsException($cacheProvider)
    {
        $cache = $cacheProvider();

        $cache->setPrefix('foo');
    }

    /**
     * @dataProvider provideTestInstanceCreators
     * @expectedException InvalidArgumentException
     */
    public function testPrefixStartingWithForwardSlashThrowsException($cacheProvider)
    {
        $cache = $cacheProvider();

        $cache->setPrefix('/foo/');
    }

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testGetLocal($cacheProvider)
    {
        $cache = $cacheProvider();

        $this->assertInstanceOf('Kuria\\Cache\\LocalCacheInterface', $cache->getLocal('foo'));
    }

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testApi($cacheProvider)
    {
        $cache = $cacheProvider();

        $this->assertFalse($cache->has('test', 'foo'), 'has() returns false for non-existent entry');
        $this->assertTrue($cache->set('test', 'foo', 123), 'set() returns true on success');
        $this->assertSame(123, $cache->get('test', 'foo'), 'get() returns the stored data');
        $this->assertFalse($cache->add('test', 'foo', 321), 'add() returns false for already existing entry');
        $this->assertSame(123, $cache->get('test', 'foo'), 'add() does not overwrite existing entry');
        $this->assertTrue($cache->add('test', 'bar', 'hello'), 'add() returns true on success');
        $this->assertSame('hello', $cache->get('test', 'bar'), 'add() returns the stored data');
        $this->assertTrue($cache->add('test', 'bar2', 'hello2'));

        $this->assertSame(124, $cache->increment('test', 'foo', 1, $incrementSuccess), 'increment() returns the new value');
        $this->assertTrue($incrementSuccess, 'increment() sets the success variable correctly');
        $this->assertSame(124, $cache->get('test', 'foo'), 'increment()ed values can be read with get()');

        $this->assertSame(123, $cache->decrement('test', 'foo', 1, $decrementSuccess), 'decrement() returns the new value');
        $this->assertTrue($decrementSuccess, 'decrement() sets the success variable correctly');
        $this->assertSame(123, $cache->get('test', 'foo'), 'decrement()ed values can be read with get()');

        $this->assertTrue($cache->remove('test', 'foo'), 'remove() returns true on success');
        $this->assertFalse($cache->remove('test', 'baz'), 'remove() returns false on failure');

        $this->assertFalse($cache->get('test', 'nonexistent'), 'get() returns false on failure');
    }

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testTypeStorage($cacheProvider)
    {
        $cache = $cacheProvider();

        // null
        $cache->set('test', 'null', null);

        $this->assertSame(null, $cache->get('test', 'null'));

        // boolean
        $boolean1 = true;
        $boolean2 = false;

        $cache->set('test', 'bool1', $boolean1);
        $cache->set('test', 'bool2', $boolean2);
        $this->assertSame($boolean1, $cache->get('test', 'bool1'));
        $this->assertSame($boolean2, $cache->get('test', 'bool2'));

        // integer
        $integer1 = 123;
        $integer2 = -123;

        $cache->set('test', 'int1', $integer1);
        $cache->set('test', 'int2', $integer2);
        $this->assertSame($integer1, $cache->get('test', 'int1'));
        $this->assertSame($integer2, $cache->get('test', 'int2'));

        // float
        $float1 = 1.23;
        $float2 = -1.23;

        $cache->set('test', 'float1', $float1);
        $cache->set('test', 'float2', $float2);
        $this->assertSame($float1, $cache->get('test', 'float1'));
        $this->assertSame($float2, $cache->get('test', 'float2'));

        // string
        $string = "foo ěščřžýáíé\nbar";

        $cache->set('test', 'string', $string);
        $this->assertSame($string, $cache->get('test', 'string'));

        // array
        $array = array(
            'data' => 'foo',
            'foo' => 'bar',
        );

        $cache->set('test', 'array', $array);
        $this->assertSame($array, $cache->get('test', 'array'));

        // object
        $object = new \stdClass();
        $object->foo = 'bar';

        $this->assertTrue($cache->set('test', 'object', $object));
        $this->assertEquals($object, $cache->get('test', 'object'));
    }

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testClear($cacheProvider)
    {
        $cache = $cacheProvider();

        $cache->set('foo', 'example', 1);
        $cache->set('bar', 'example', 1);

        if ($cache->supportsClearingCategory()) {
            $cache->clear('foo');

            $this->assertFalse($cache->has('foo', 'example'), 'clear($category) removes keys from given category');
            $this->assertTrue($cache->has('bar', 'example'), 'clear($category) does not remove keys from other categories');
        } else {
            $this->assertFalse($cache->clear('foo'), 'clear(category) should fail if the cache does not support it');
            $this->assertTrue($cache->has('foo', 'example'));
        }

        $cache->clear();

        $this->assertFalse($cache->has('bar', 'example'), 'clear() removes all keys');
    }

    /**
     * @dataProvider provideTestInstanceCreators
     * @expectedException InvalidArgumentException
     */
    public function testIncrementStepLessThanOneThrowsException($cacheProvider)
    {
        $cache = $cacheProvider();

        $cache->increment('foo', 'bar', 0);
    }

    /**
     * @dataProvider provideTestInstanceCreators
     * @expectedException InvalidArgumentException
     */
    public function testDecrementStepLessThanOneThrowsException($cacheProvider)
    {
        $cache = $cacheProvider();

        $cache->decrement('foo', 'bar', 0);
    }
}
