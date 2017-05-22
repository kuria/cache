<?php

namespace Kuria\Cache\Extension;

class CacheExtensionTest extends \PHPUnit_Framework_TestCase
{
    private function getTestCacheExtenstion()
    {
        return new TestCacheExtension();
    }

    public function testSetPriority()
    {
        $ext = $this->getTestCacheExtenstion();

        $ext
            ->setPriority('foo', 5)
            ->setPriority('bar', -5);

        $events = $ext->getEvents();
        $expectedEvents = array(
            'foo' => array('onFoo', 5),
            'bar' => array('onFoo', -5),
        );

        $this->assertSame($expectedEvents, $events);
    }

    /**
     * @expectedException        OutOfBoundsException
     * @expectedExceptionMessage Unknown priority key
     */
    public function testExceptionOnUnknownPriorityKey()
    {
        $this->getTestCacheExtenstion()->setPriority('invalid', 123);
    }
}

class TestCacheExtension extends CacheExtension
{
    protected $priorities = array(
        'foo' => 100,
        'bar' => -100,
    );

    public function getEvents()
    {
        return array(
            'foo' => array('onFoo', $this->priorities['foo']),
            'bar' => array('onFoo', $this->priorities['bar']),
        );
    }
}
