<?php

namespace Kuria\Cache;

use Kuria\Cache\Provider\MemoryCache;

class LocalCacheTest extends \PHPUnit_Framework_TestCase
{
    public function testApi()
    {
        $local = new LocalCache(new MemoryCache(), 'test');

        $this->assertFalse($local->has('foo'), 'has() returns false for non-existent entry');
        $this->assertTrue($local->set('foo', 123), 'set() returns true on success');
        $this->assertSame(123, $local->get('foo'), 'get() returns the stored data');
        $this->assertFalse($local->add('foo', 321), 'add() returns false for already existing entry');
        $this->assertSame(123, $local->get('foo'), 'add() does not overwrite existing entry');
        $this->assertTrue($local->add('bar', 'hello'), 'add() returns true on success');
        $this->assertSame('hello', $local->get('bar'), 'add() returns the stored data');

        $this->assertSame(124, $local->increment('foo', 1, $incrementSuccess), 'increment() returns the new value');
        $this->assertTrue($incrementSuccess, 'increment() sets the success variable correctly');

        $this->assertSame(123, $local->decrement('foo', 1, $decrementSuccess), 'decrement() returns the new value');
        $this->assertTrue($decrementSuccess, 'decrement() sets the success variable correctly');

        $this->assertTrue($local->remove('foo'), 'remove() returns true on success');
    }

    public function testClear()
    {
        $local = new LocalCache(new MemoryCache(), 'test');

        $local->set('example', 1);
        $local->clear();

        $this->assertFalse($local->has('example'), 'clear() removes all keys');
    }
}
