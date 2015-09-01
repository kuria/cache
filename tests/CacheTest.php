<?php

namespace Kuria\Cache;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\TestFilterableDriver;
use Kuria\Cache\Driver\TestMultipleFetchDriver;
use Kuria\Event\EventSubscriber;
use Kuria\Event\EventSubscriberInterface;

class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return DriverInterface
     */
    private function getDriverMock()
    {
        return $this->getMock(__NAMESPACE__ . '\Driver\DriverInterface');
    }

    /**
     * @return EventSubscriberInterface
     */
    private function getSubscriberMock()
    {
        return $this->getMockForAbstractClass(__NAMESPACE__ . '\TestEventSubscriber');
    }

    /**
     * @return TestFilterableDriver
     */
    private function getFilterableDriverMock()
    {
        return $this->getMockForAbstractClass(__NAMESPACE__ . '\Driver\TestFilterableDriver');
    }
    
     /**
      * @return TestMultipleFetchDriver
      */
    private function getMultipleFetchDriverMock()
    {
        return $this->getMockForAbstractClass(__NAMESPACE__ . '\Driver\TestMultipleFetchDriver');
    }   

    public function testCommonApi()
    {
        $testValue = 'test-value';
        $existingTestValue = 'existing-test-value';

        $driverMock = $this->getDriverMock();

        $driverMock
            ->expects($this->exactly(2))
            ->method('exists')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz'))
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false
            )
        ;

        $driverMock
            ->expects($this->exactly(2))
            ->method('fetch')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz'))
            )
            ->willReturnOnConsecutiveCalls(
                $existingTestValue,
                false
            )
        ;

        $driverMock
            ->expects($this->exactly(2))
            ->method('store')
            ->withConsecutive(
                array(
                    $this->identicalTo('foo.bar'),
                    $this->identicalTo($testValue),
                    $this->isFalse(),
                    $this->identicalTo(0)
                ),
                array(
                    $this->identicalTo('foo.baz'),
                    $this->identicalTo($testValue),
                    $this->isTrue(),
                    $this->identicalTo(60)
                )
            )
            ->willReturnOnConsecutiveCalls(
                false,
                true
            )
        ;

        $driverMock
            ->expects($this->exactly(2))
            ->method('expunge')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz'))
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false
            )
        ;

        $driverMock
            ->expects($this->exactly(2))
            ->method('modifyInteger')
            ->withConsecutive(
                array(
                    $this->identicalTo('foo.bar'),
                    $this->identicalTo(1),
                    $this->anything()
                ),
                array(
                    $this->identicalTo('foo.baz'),
                    $this->identicalTo(-1),
                    $this->anything()
                )
            )
            ->willReturnCallback(function ($key, $offset, &$success) {
                if ('foo.bar' === $key) {
                    $success = true;

                    return 2;
                } else {
                    $success = false;

                    return false;
                }
            })
        ;

        $cache = new Cache($driverMock, 'foo.');

        $this->assertInstanceOf(__NAMESPACE__ . '\CacheInterface', $cache->getNamespace('lorem.'));
        $this->assertTrue($cache->has('bar'));
        $this->assertFalse($cache->has('baz'));
        $this->assertSame($existingTestValue, $cache->get('bar'));
        $this->assertFalse($cache->get('baz'));
        $this->assertFalse($cache->add('bar', $testValue));
        $this->assertTrue($cache->set('baz', $testValue, 60));
        $this->assertTrue($cache->remove('bar'));
        $this->assertFalse($cache->remove('baz'));
        $this->assertSame(2, $cache->increment('bar', 1, $success));
        $this->assertTrue($success);
        $this->assertFalse($cache->decrement('baz', 1, $success));
        $this->assertFalse($success);
    }

    public function testGetMultipleWithoutDriverSupport()
    {
        $driverMock = $this->getDriverMock();

        $driverMock
            ->expects($this->exactly(4))
            ->method('fetch')
            ->withConsecutive(
                array($this->identicalTo('test.foo')),
                array($this->identicalTo('test.foo')),
                array($this->identicalTo('test.bar')),
                array($this->identicalTo('test.baz'))
            )
            ->willReturnOnConsecutiveCalls(
                false,
                1,
                2,
                false
            )
        ;

        $cache = new Cache($driverMock, 'test.');

        $this->assertSame(array(), $cache->getMultiple(array()));
        $this->assertSame(array('foo' => false), $cache->getMultiple(array('foo')));

        $cache->add('foo', 1);
        $cache->add('bar', 2);

        $values = $cache->getMultiple(array('foo', 'bar', 'baz'));

        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('foo', $values);
        $this->assertArrayHasKey('bar', $values);
        $this->assertArrayHasKey('baz', $values);

        $this->assertSame(1, $values['foo']);
        $this->assertSame(2, $values['bar']);
        $this->assertFalse($values['baz']);
    }
    
    public function testGetMultipleWithDriverSupport()
    {
        $driverMock = $this->getMultipleFetchDriverMock();

        $driverMock
            ->expects($this->exactly(3))
            ->method('fetchMultiple')
            ->withConsecutive(
                array($this->identicalTo(array())),
                array($this->identicalTo(array('test.foo'))),
                array($this->identicalTo(array('test.foo', 'test.bar', 'test.baz')))
            )
            ->willReturnOnConsecutiveCalls(
                array(),
                array('test.foo' => false),
                array('test.foo' => 1, 'test.bar' => 2, 'test.baz' => false)
            )
        ;

        $driverMock
            ->expects($this->never())
            ->method('fetch')
        ;
        
        $cache = new Cache($driverMock, 'test.');
        
        $this->assertSame(array(), $cache->getMultiple(array()));
        $this->assertSame(array('foo' => false), $cache->getMultiple(array('foo')));

        $cache->add('foo', 1);
        $cache->add('bar', 2);

        $values = $cache->getMultiple(array('foo', 'bar', 'baz'));

        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('foo', $values);
        $this->assertArrayHasKey('bar', $values);
        $this->assertArrayHasKey('baz', $values);

        $this->assertSame(1, $values['foo']);
        $this->assertSame(2, $values['bar']);
        $this->assertFalse($values['baz']);
    }

    public function testCached()
    {
        $that = $this;
        
        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getSubscriberMock();

        $subscriberMock
            ->expects($this->exactly(2))
            ->method('onFetchA')
            ->willReturnCallback(function (array $event) use ($that) {
                $that->assertSame('foo', $event['key']);
                $that->assertSame(array('test-fetch-option' => 'potato'), $event['options']);
            })
        ;

        $subscriberMock
            ->expects($this->once())
            ->method('onStoreA')
            ->willReturnCallback(function (array $event) use ($that) {
                $that->assertSame(123, $event['ttl']);
                $that->assertSame('new-value', $event['value']);
                $that->assertSame(array('test-store-option' => 'hello'), $event['options']);
            })
        ;

        $driverMock
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                false,
                'existing-value'
            )
        ;

        $driverMock
            ->expects($this->once())
            ->method('store')
            ->with(
                $this->identicalTo('foo'),
                $this->identicalTo('new-value'),
                $this->isFalse(),
                $this->identicalTo(123)
            )
        ;

        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $result = $cache->cached(
            'foo',
            function (&$ttl, &$options) use ($that) {
                $that->assertSame(0, $ttl);
                $that->assertInternalType('array', $options);
                $that->assertEmpty($options);

                $ttl = 123;
                $options['test-store-option'] = 'hello';

                return 'new-value';
            },
            array('test-fetch-option' => 'potato')
        );

        $this->assertSame('new-value', $result);
        
        $result = $cache->cached(
            'foo', 
            function () use ($that) {
                $that->fail('The callback must not be called for existing values');
            },
            array('test-fetch-option' => 'potato')
        );

        $this->assertSame('existing-value', $result);
    }

    public function testNonFilterable()
    {
        $driverMock = $this->getDriverMock();

        $driverMock
            ->expects($this->exactly(2))
            ->method('purge')
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $cache = new Cache($driverMock);

        $this->assertFalse($cache->canFilter());
        $this->assertFalse($cache->filter('foo'));
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->clear());
    }

    public function testFilterable()
    {
        $driverMock = $this->getFilterableDriverMock();

        $driverMock
            ->expects($this->exactly(3))
            ->method('filter')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz')),
                array($this->identicalTo('foo.'))
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false,
                true
            )
        ;

        $driverMock
            ->expects($this->never())
            ->method('purge')
        ;

        $cache = new Cache($driverMock, 'foo.');

        $this->assertTrue($cache->canFilter());
        $this->assertTrue($cache->filter('bar'));
        $this->assertFalse($cache->filter('baz'));
        $this->assertTrue($cache->clear());
    }

    /**
     * @dataProvider provideInvalidPrefixes
     * @expectedException InvalidArgumentException
     */
    public function testExceptionOnInvalidPrefix($prefix)
    {
        new Cache($this->getDriverMock(), $prefix);
    }

    public function provideInvalidPrefixes()
    {
        // Only alphanumeric characters, underscores and a dots are allowed.
        // The prefix must begin with an alphanumeric character and must not
        // contain consecutive dots.

        return array(
            array('foo+bar'), // invalid character
            array('.foo'), // invalid first character
            array('foo..bar'), // consecutive dots
            array('foo.bar..'), // consecutive dots at end
        );
    }

    /**
     * @dataProvider provideInvalidKeys
     * @expectedException InvalidArgumentException
     */
    public function testExceptionOnInvalidKey($key)
    {
        $cache = new Cache($this->getDriverMock());

        $cache->get($key);
    }

    public function provideInvalidKeys()
    {
        // Only alphanumeric characters, underscores and a dots are allowed.
        // The key must begin and end with an alphanumeric character and must
        // not contain consecutive dots.

        return array(
            array('foo+bar'), // invalid character
            array('.foo'), // invalid first character
            array('foo.'), // invalid last character
            array('foo..bar'), // consecutive dots
        );
    }

    /**
     * @dataProvider provideInvalidSteps
     * @expectedException InvalidArgumentException
     */
    public function testExceptionOnInvalidIncrementStep($step)
    {
        $cache = new Cache($this->getDriverMock());

        $cache->increment('foo', $step);
    }

    /**
     * @dataProvider provideInvalidSteps
     * @expectedException InvalidArgumentException
     */
    public function testExceptionOnInvalidDecrementStep($step)
    {
        $cache = new Cache($this->getDriverMock());

        $cache->decrement('foo', $step);
    }

    public function provideInvalidSteps()
    {
        return array(
            array(0),
            array(0.45),
            array(-1),
            array(-0.1),
            array(-100),
        );
    }

    /**
     * @dataProvider provideStoreMethodNames
     */
    public function testStoreEvent($storeMethod)
    {
        $that = $this;

        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getMockForAbstractClass(__NAMESPACE__ . '\TestEventSubscriber');
        
        $subscriberMock
            ->expects($this->once())
            ->method('onStoreA')
            ->willReturnCallback(function ($event) use ($that) {
                $that->assertInternalType('array', $event);

                $that->assertArrayHasKey('key', $event);
                $that->assertSame('foo', $event['key']);

                $that->assertArrayHasKey('value', $event);
                $that->assertSame('test-value', $event['value']);

                $that->assertArrayHasKey('ttl', $event);
                $that->assertSame(60, $event['ttl']);

                $that->assertArrayHasKey('options', $event);
                $that->assertSame(array('some-option' => 'some-value'), $event['options']);

                // the value, ttl and options keys should be references
                $event['value'] = 'changed-test-value';
                $event['ttl'] = 120;
                $event['options']['extra-option'] = 'hello';
            })
        ;
            
        $subscriberMock
            ->expects($this->once())
            ->method('onStoreB')
            ->willReturnCallback(function ($event) use ($that) {
                // ensure that by-reference attribute changes are propagated to other event listeners
                $that->assertSame('foo', $event['key']);
                $that->assertSame('changed-test-value', $event['value']);
                $that->assertSame(120, $event['ttl']);
                $that->assertSame(array('some-option' => 'some-value', 'extra-option' => 'hello'), $event['options']);
            })
        ;

        $driverMock
            ->expects($this->once())
            ->method('store')
            ->willReturnCallback(function ($key, $value, $overwrite, $ttl) use ($that) {
                // ensure that by-reference attribute changes are propagated to the driver
                $that->assertSame('foo', $key);
                $that->assertSame('changed-test-value', $value);
                $that->assertSame(120, $ttl);

                return true;
            })
        ;
        
        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $this->assertTrue($cache->$storeMethod('foo', 'test-value', 60, array('some-option' => 'some-value')));
    }

    public function provideStoreMethodNames()
    {
        return array(
            array('add'),
            array('set'),
        );
    }

    public function testFetchEvent()
    {
        $that = $this;

        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getSubscriberMock();

        $driverMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn('test-value')
        ;

        $subscriberMock
            ->expects($this->once())
            ->method('onFetchA')
            ->willReturnCallback(function ($event) use ($that) {
                $that->assertInternalType('array', $event);

                $that->assertArrayHasKey('key', $event);
                $that->assertSame('foo', $event['key']);

                $that->assertArrayHasKey('options', $event);
                $that->assertSame(array('some-option' => 'some-value'), $event['options']);

                $that->assertArrayHasKey('found', $event);
                $that->assertTrue($event['found']);

                $that->assertArrayHasKey('value', $event);
                $that->assertSame('test-value', $event['value']);

                // the value and options keys should be references
                $event['value'] = 'changed-test-value';
                $event['options']['extra-option'] = 'hello';
            })
        ;

        $subscriberMock
            ->expects($this->once())
            ->method('onFetchB')
            ->willReturnCallback(function ($event) use ($that) {
                // ensure that by-reference attribute changes are propagated to other event listeners
                $that->assertSame('foo', $event['key']);
                $that->assertSame('changed-test-value', $event['value']);
                $that->assertSame(array('some-option' => 'some-value', 'extra-option' => 'hello'), $event['options']);
            })
        ;

        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $this->assertSame('changed-test-value', $cache->get('foo', array('some-option' => 'some-value')));
    }

    public function testFetchEventDuringGetMultiple()
    {
        $that = $this;

        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getMockForAbstractClass(__NAMESPACE__ . '\TestEventSubscriber');

        $invocationCounterA = 0;
        $invocationCounterB = 0;

        $invocationMap = array(
            array('key' => 'foo', 'value' => 'original-foo-value'),
            array('key' => 'bar', 'value' => 'original-bar-value'),
            array('key' => 'baz', 'value' => false),
        );

        $driverMock
            ->expects($this->exactly(3))
            ->method('fetch')
            ->withConsecutive(
                array($this->identicalTo($invocationMap[0]['key'])),
                array($this->identicalTo($invocationMap[1]['key'])),
                array($this->identicalTo($invocationMap[2]['key']))
            )
            ->willReturnOnConsecutiveCalls(
                $invocationMap[0]['value'],
                $invocationMap[1]['value'],
                $invocationMap[2]['value']
            )
        ;

        $subscriberMock
            ->expects($this->exactly(3))
            ->method('onFetchA')
            ->willReturnCallback(function ($event) use ($that, $invocationMap, &$invocationCounterA) {
                $current = $invocationMap[$invocationCounterA];

                $that->assertInternalType('array', $event);

                $that->assertArrayHasKey('key', $event);
                $that->assertSame($current['key'], $event['key']);

                $that->assertArrayHasKey('options', $event);
                $that->assertSame(array('some-option' => 'some-value'), $event['options']);

                $that->assertArrayHasKey('found', $event);
                $that->assertSame(false !== $current['value'], $event['found']);

                $that->assertArrayHasKey('value', $event);
                $that->assertSame($current['value'], $event['value']);

                // the value and options keys should be references
                $event['value'] = "changed-{$current['key']}-value";
                $event['options']["extra-{$current['key']}-option"] = $current['key'];

                ++$invocationCounterA;
            })
        ;

        $subscriberMock
            ->expects($this->exactly(3))
            ->method('onFetchB')
            ->willReturnCallback(function ($event) use ($that, $invocationMap, &$invocationCounterB) {
                $current = $invocationMap[$invocationCounterB];

                // ensure that by-reference attribute changes are propagated to other event listeners
                $that->assertSame($current['key'], $event['key']);
                $that->assertSame("changed-{$current['key']}-value", $event['value']);
                $that->assertSame(array('some-option' => 'some-value', "extra-{$current['key']}-option" => $current['key']), $event['options']);

                ++$invocationCounterB;
            })
        ;

        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $this->assertSame(
            array(
                'foo' => 'changed-foo-value',
                'bar' => 'changed-bar-value',
                'baz' => 'changed-baz-value'
            ),
            $cache->getMultiple(
                array('foo', 'bar', 'baz'),
                array('some-option' => 'some-value')
            )
        );
    }

    public function testFetchEventFiresForNonexistentEntries()
    {
        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getSubscriberMock();

        $subscriberMock
            ->expects($this->exactly(2))
            ->method('onFetchA')
            ->with(
                $this->callback(function ($event) {
                    return false === $event['value'];
                })
            )
        ;

        $driverMock
            ->expects($this->any())
            ->method('fetch')
            ->willReturn(false)
        ;

        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $this->assertFalse($cache->get('foo'));
        $this->assertFalse($cache->get('bar'));
    }

    public function testFetchEventCanDiscardValues()
    {
        $driverMock = $this->getDriverMock();
        $subscriberMock = $this->getSubscriberMock();

        $driverMock
            ->expects($this->exactly(4))
            ->method('expunge')
            ->withConsecutive(
                array($this->identicalTo('foo')),
                array($this->identicalTo('bar')),
                array($this->identicalTo('lorem')),
                array($this->identicalTo('ipsum'))
            )
            ->willReturn(true)
        ;

        $driverMock
            ->expects($this->any())
            ->method('fetch')
            ->willReturnCallback(function ($key) {
                switch ($key) {
                    case 'foo':
                    case 'bar':
                    case 'lorem':
                    case 'ipsum':
                        return "{$key}-value";
                    default:
                        return false;
                }
            })
        ;

        $subscriberMock
            ->expects($this->exactly(6))
            ->method('onFetchA')
            ->willReturnCallback(function ($event) {
                $event['value'] = false; // discard
            })
        ;

        $cache = new Cache($driverMock);
        $cache->subscribe($subscriberMock);

        $this->assertFalse($cache->get('foo'));
        $this->assertFalse($cache->get('bar'));
        $this->assertFalse($cache->get('nonexistent'));

        $this->assertSame(array('lorem' => false, 'ipsum' => false, 'nonexistent2' => false), $cache->getMultiple(array('lorem', 'ipsum', 'nonexistent2')));
    }
}

abstract class TestEventSubscriber extends EventSubscriber
{
    public function getEvents()
    {
        return array(
            'store' => array(
                array('onStoreA', 10),
                array('onStoreB', 0),
            ),
            'fetch' => array(
                array('onFetchA', 10),
                array('onFetchB', 0),
            ),
        );
    }

    abstract public function onStoreA($event);
    abstract public function onStoreB($event);
    abstract public function onFetchA($event);
    abstract public function onFetchB($event);
}
