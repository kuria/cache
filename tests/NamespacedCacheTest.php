<?php

namespace Kuria\Cache;

class NamespacedCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return CacheInterface
     */
    protected function getWrappedCacheMock()
    {
        return $this->getMock(__NAMESPACE__ . '\CacheInterface');
    }

    public function testCommonApi()
    {
        $wrappedCacheMock = $this->getWrappedCacheMock();

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('has')
            ->with($this->identicalTo('foo.bar'))
            ->willReturn(true)
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with(
                $this->identicalTo('foo.bar'),
                $this->identicalTo(array('some-option' => 'some-value'))
            )
            ->willReturn('test-value')
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('getMultiple')
            ->with(
                $this->identicalTo(array('foo.bar', 'foo.baz')),
                $this->identicalTo(array('some-option' => 'some-value'))
            )
            ->willReturn(array(
                'foo.bar' => 'test-value',
                'foo.baz' => false,
            ))
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('add')
            ->with(
                $this->identicalTo('foo.bar'),
                $this->identicalTo('test-value'),
                $this->identicalTo(123),
                $this->identicalTo(array('some-option' => 'some-value'))
            )
            ->willReturn(true)
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('set')
            ->with(
                $this->identicalTo('foo.baz'),
                $this->identicalTo('test-value-2'),
                $this->identicalTo(456),
                $this->identicalTo(array('some-other-option' => 'some-other-value'))
            )
            ->willReturn(false)
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('increment')
            ->with(
                $this->identicalTo('foo.bar'),
                $this->identicalTo(5)
            )
            ->willReturnCallback(function ($key, $step, &$success = null) {
                $success = true;

                return 123;
            })
        ;

        $wrappedCacheMock
            ->expects($this->atLeastOnce())
            ->method('decrement')
            ->with(
                $this->identicalTo('foo.baz'),
                $this->identicalTo(10)
            )
            ->willReturnCallback(function ($key, $step, &$success = null) {
                $success = false;

                return false;
            })
        ;
            
        $wrappedCacheMock
            ->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz'))
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false
            )
        ;

        $namespacedCache = new NamespacedCache($wrappedCacheMock, 'foo.');

        $this->assertTrue($namespacedCache->has('bar'));
        $this->assertSame('test-value', $namespacedCache->get('bar', array('some-option' => 'some-value')));
        $this->assertSame(array('bar' => 'test-value', 'baz' => false), $namespacedCache->getMultiple(array('bar', 'baz'), array('some-option' => 'some-value')));
        $this->assertTrue($namespacedCache->add('bar', 'test-value', 123, array('some-option' => 'some-value')));
        $this->assertFalse($namespacedCache->set('baz', 'test-value-2', 456, array('some-other-option' => 'some-other-value')));
        $this->assertSame(123, $namespacedCache->increment('bar', 5, $success));
        $this->assertTrue($success);
        $this->assertFalse($namespacedCache->decrement('baz', 10, $success));
        $this->assertFalse($success);
        $this->assertTrue($namespacedCache->remove('bar'));
        $this->assertFalse($namespacedCache->remove('baz'));
    }

    public function testGetNamespace()
    {
        $wrappedCacheMock = $this->getWrappedCacheMock();

        $wrappedCacheMock
            ->expects($this->once())
            ->method('get')
            ->with($this->identicalTo('foo.bar.baz'))
            ->willReturn('test-value')
        ;

        $namespacedCache = new TestNamespacedCache($wrappedCacheMock, 'foo.');
        $this->assertSame($wrappedCacheMock, $namespacedCache->getWrappedCache());

        // calling getNamespace() on an already namespaced cache should
        // simply combine the prefixes, not create another level of wrapping
        $nestedNamespacedCache = $namespacedCache->getNamespace('bar.');
        $this->assertSame($wrappedCacheMock, $nestedNamespacedCache->getWrappedCache());

        $this->assertSame('test-value', $nestedNamespacedCache->get('baz'));
    }

    public function testCached()
    {
        $cachedCallback = function () {};

        $wrappedCacheMock = $this->getWrappedCacheMock();

        $wrappedCacheMock
            ->expects($this->once())
            ->method('cached')
            ->with(
                $this->identicalTo('foo.bar'),
                $this->identicalTo($cachedCallback),
                $this->identicalTo(array('test-fetch-option' => 'banana'))
            )
            ->willReturn('test-value')
        ;

        $namespacedCache = new NamespacedCache($wrappedCacheMock, 'foo.');

        $result = $namespacedCache->cached(
            'bar',
            $cachedCallback,
            array('test-fetch-option' => 'banana')
        );

        $this->assertSame('test-value', $result);
    }
    
    public function testNonFilterable()
    {
        $wrappedCacheMock = $this->getWrappedCacheMock();

        $wrappedCacheMock
            ->expects($this->any())
            ->method('canFilter')
            ->willReturn(false)
        ;

        $wrappedCacheMock
            ->expects($this->exactly(2))
            ->method('filter')
            ->withConsecutive(
                array($this->identicalTo('foo.bar')),
                array($this->identicalTo('foo.baz'))/*,
                array($this->identicalTo('foo.'))*/
            )
            ->willReturnOnConsecutiveCalls(
                false,
                false/*,
                false*/
            )
        ;

        $wrappedCacheMock
            ->expects($this->once())
            ->method('clear')
            ->willReturn(true)
        ;

        $namespacedCache = new NamespacedCache($wrappedCacheMock, 'foo.');

        $this->assertFalse($namespacedCache->canFilter());
        $this->assertFalse($namespacedCache->filter('bar'));
        $this->assertFalse($namespacedCache->filter('baz'));
        $this->assertTrue($namespacedCache->clear());
    }

    public function testFilterable()
    {
        $wrappedCacheMock = $this->getWrappedCacheMock();

        $wrappedCacheMock
            ->expects($this->any())
            ->method('canFilter')
            ->willReturn(true)
        ;

        $wrappedCacheMock
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

        $wrappedCacheMock
            ->expects($this->never())
            ->method('clear')
        ;

        $namespacedCache = new NamespacedCache($wrappedCacheMock, 'foo.');

        $this->assertTrue($namespacedCache->canFilter());
        $this->assertTrue($namespacedCache->filter('bar'));
        $this->assertFalse($namespacedCache->filter('baz'));
        $this->assertTrue($namespacedCache->clear());
    }

    /**
     * @dataProvider provideEmptyPrefixes
     * @expectedException InvalidArgumentException
     */
    public function testExceptionOnEmptyPrefix($prefix)
    {
        new NamespacedCache($this->getWrappedCacheMock(), $prefix);
    }

    public function provideEmptyPrefixes()
    {
        return array(
            array(''),
            array(null),
        );
    }
}

class TestNamespacedCache extends NamespacedCache
{
    public function getWrappedCache()
    {
        return $this->wrappedCache;
    }
}
