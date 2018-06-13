<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Cache\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CacheItemPoolTest extends TestCase
{
    /** @var CacheInterface|MockObject */
    private $cacheMock;
    /** @var CacheItemPool */
    private $pool;

    protected function setUp()
    {
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->pool = new CacheItemPool($this->cacheMock);
    }

    /**
     * @dataProvider provideCachedItems
     */
    function testShouldGetItemFromCache(string $key, $value, bool $exists)
    {
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturnCallback(function ($key, &$existsRef) use ($value, $exists) {
                $existsRef = $exists;

                return $value;
            });

        $item = $this->pool->getItem($key);

        $this->assertSame($key, $item->getKey());
        $this->assertSame($value, $item->get());
        $this->assertSame($exists, $item->isHit());
    }

    function provideCachedItems(): array
    {
        return [
            // key, value, exists
            ['foo', 'bar', true],
            ['baz', 123, true],
            ['qux', null, false],
        ];
    }

    function testShouldGetItemsFromCache()
    {
        $this->cacheMock->expects($this->once())
            ->method('getMultiple')
            ->with(['foo', 'bar', 'baz'])
            ->willReturnCallback(function ($keys, &$failedKeys) {
                $failedKeys = ['baz'];

                return ['foo' => 'a', 'bar' => 'b'];
            });

        $this->assertEquals(
            [
                'foo' => new CacheItem('foo', 'a', true),
                'bar' => new CacheItem('bar', 'b', true),
                'baz' => new CacheItem('baz', null, false),
            ],
            $this->pool->getItems(['foo', 'bar', 'baz'])
        );
    }

    function testShouldCheckIfItemExists()
    {
        $this->cacheMock->expects($this->exactly(3))
            ->method('has')
            ->withConsecutive(
                ['foo'],
                ['bar'],
                ['baz']
            )
            ->willReturnOnConsecutiveCalls(
                true,
                true,
                false
            );

        $this->assertTrue($this->pool->hasItem('foo'));
        $this->assertTrue($this->pool->hasItem('bar'));
        $this->assertFalse($this->pool->hasItem('baz'));
    }

    /**
     * @dataProvider provideBooleanResults
     */
    function testShouldClear(bool $result)
    {
        $this->cacheMock->expects($this->once())
            ->method('clear')
            ->willReturn($result);

        $this->assertSame($result, $this->pool->clear());
    }

    /**
     * @dataProvider provideBooleanResults
     */
    function testShouldDeleteItem(bool $result)
    {
        $this->cacheMock->expects($this->once())
            ->method('delete')
            ->with('key')
            ->willReturn($result);

        $this->assertSame($result, $this->pool->deleteItem('key'));
    }

    /**
     * @dataProvider provideBooleanResults
     */
    function testShouldDeleteItems(bool $result)
    {
        $this->cacheMock->expects($this->once())
            ->method('deleteMultiple')
            ->with(['foo', 'bar', 'baz'])
            ->willReturn($result);

        $this->assertSame($result, $this->pool->deleteItems(['foo', 'bar', 'baz']));
    }

    /**
     * @dataProvider provideItemsToSave
     */
    function testShouldSaveItem(string $key, $value, ?int $ttl, bool $result)
    {
        $this->cacheMock->expects($this->once())
            ->method('set')
            ->with($key, $this->identicalTo($value), $this->identicalTo($ttl))
            ->willReturn($result);

        $item = new CacheItem($key, $value, false);
        $item->expiresAfter($ttl);

        $this->assertSame($result, $this->pool->save($item));
    }

    function provideItemsToSave(): array
    {
        return [
            // key, value, ttl, result
            ['foo', 'bar', null, true],
            ['baz', 123, 60, true],
            ['qux', true, 0, false],
        ];
    }

    function testShouldSaveAndGetDeferred()
    {
        $this->cacheMock->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive(
                ['foo'],
                ['bar'],
                ['baz']
            )
            ->willReturnCallback($this->getNonexistentItemCallback());

        $this->cacheMock->expects($this->once())
            ->method('getMultiple')
            ->with(['qux', 'mlem', 'boop'])
            ->willReturnCallback(function ($keys, &$failedKeys) {
                $failedKeys = ['qux', 'mlem', 'boop'];

                return ['qux' => null, 'mlem' => null, 'boop' => null];
            });

        $foo = $this->pool->getItem('foo');
        $bar = $this->pool->getItem('bar');
        $baz = $this->pool->getItem('baz');
        $multi = $this->pool->getItems(['qux', 'mlem', 'boop']);

        $this->assertTrue($this->pool->saveDeferred($foo));
        $this->assertTrue($this->pool->saveDeferred($bar));
        $this->assertTrue($this->pool->saveDeferred($baz));
        $this->assertTrue($this->pool->saveDeferred($multi['qux']));
        $this->assertTrue($this->pool->saveDeferred($multi['mlem']));
        $this->assertTrue($this->pool->saveDeferred($multi['boop']));

        $this->assertSame($foo, $this->pool->getItem('foo'));
        $this->assertSame($bar, $this->pool->getItem('bar'));
        $this->assertSame($baz, $this->pool->getItem('baz'));
        $this->assertSame($multi, $this->pool->getItems(['qux', 'mlem', 'boop']));
    }

    /**
     * @dataProvider provideBooleanResults
     */
    function testShouldCommitDeferred($success)
    {
        $this->cacheMock->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive(
                ['foo'],
                ['bar'],
                ['baz']
            )
            ->willReturnCallback($this->getNonexistentItemCallback());

        $this->cacheMock->expects($this->exactly(2))
            ->method('setMultiple')
            ->withConsecutive(
                [['foo' => 'value'], null],
                [['bar' => 123, 'baz' => true], true]
            )
            ->willReturn($success);

        $foo = $this->pool->getItem('foo');
        $bar = $this->pool->getItem('bar');
        $baz = $this->pool->getItem('baz');

        $foo->set('value');
        $bar->set(123);
        $bar->expiresAfter(5);
        $baz->set(true);
        $baz->expiresAfter(5);

        $this->assertTrue($this->pool->saveDeferred($foo));
        $this->assertTrue($this->pool->saveDeferred($bar));
        $this->assertTrue($this->pool->saveDeferred($baz));

        $this->assertSame($success, $this->pool->commit());
    }

    /**
     * @dataProvider provideBooleanResults
     */
    function testShouldAutoCommitOnCountAndDestruction(bool $success)
    {
        $this->assertNull($this->pool->getAutoCommitCount());

        $this->pool->setAutoCommitCount(5);

        $this->assertSame(5, $this->pool->getAutoCommitCount());

        $items = [];

        for ($i = 0; $i < 7; ++$i) {
            $items["item.{$i}"] = new CacheItem("item.{$i}", $i, false);
        }

        $this->cacheMock->expects($this->exactly(2))
            ->method('setMultiple')
            ->withConsecutive(
                [
                    [
                        'item.0' => 0,
                        'item.1' => 1,
                        'item.2' => 2,
                        'item.3' => 3,
                        'item.4' => 4,
                    ],
                    null,
                ],
                [
                    [
                        'item.5' => 5,
                        'item.6' => 6,
                    ],
                    null,
                ]
            )
            ->willReturn($success);

        foreach ($items as $item) {
            // 5th iteration should trigger autocommit
            $this->pool->saveDeferred($item);
        }

        unset($this->pool); // trigger destructor
    }

    function testShouldDeleteDeferredItems()
    {
        $this->cacheMock
            ->method('get')
            ->willReturnCallback($this->getNonexistentItemCallback());

        $this->cacheMock->expects($this->never())
            ->method('setMultiple');

        $foo = $this->pool->getItem('foo');
        $bar = $this->pool->getItem('bar');
        $baz = $this->pool->getItem('baz');
        $qux = $this->pool->getItem('qux');

        $this->assertTrue($this->pool->saveDeferred($foo));
        $this->assertTrue($this->pool->saveDeferred($bar));
        $this->assertTrue($this->pool->saveDeferred($baz));
        $this->assertTrue($this->pool->saveDeferred($qux));

        $this->pool->deleteItem('foo');
        $this->pool->deleteItem('bar');
        $this->pool->deleteItems(['baz', 'qux']);

        $this->assertTrue($this->pool->commit());
    }

    function testShouldClearDeferredItems()
    {
        $this->cacheMock
            ->method('get')
            ->willReturnCallback($this->getNonexistentItemCallback());

        $this->cacheMock->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->cacheMock->expects($this->never())
            ->method('setMultiple');

        $foo = $this->pool->getItem('foo');
        $bar = $this->pool->getItem('bar');

        $this->assertTrue($this->pool->saveDeferred($foo));
        $this->assertTrue($this->pool->saveDeferred($bar));

        $this->assertTrue($this->pool->clear());

        $this->assertTrue($this->pool->commit());
    }

    /**
     * @dataProvider provideInvalidKeys
     */
    function testShouldRejectInvalidKeys($invalidKey, string $expectedMessage)
    {
        $operations = [
            'getItem' => [$invalidKey],
            'getItems' => [['validKey', $invalidKey]],
            'hasItem' => [$invalidKey],
            'deleteItem' => [$invalidKey],
            'deleteItems' => [['validKey', $invalidKey]],
        ];

        foreach ($operations as $method => $args) {
            $e = null;
            try {
                $this->pool->{$method}(...$args);
            } catch (InvalidKeyException $e) {
            }

            $this->assertNotNull($e, sprintf('Expected InvalidKeyException to be thrown by %s()', $method));
            $this->assertSame($expectedMessage, $e->getMessage(), sprintf('Expected correct exception message from %s()', $method));
        }
    }

    function provideInvalidKeys(): array
    {
        return [
            // key, expectedMessage
            ['foo{bar}', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "foo{bar}"'],
            ['(baz-qux)', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "(baz-qux)"'],
            ['/mlem', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "/mlem"'],
            ['\\boop', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "\\boop"'],
            ['lorem@ipsum', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "lorem@ipsum"'],
            ['dolor:sit', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "dolor:sit"'],
            ['{}()/\\@:', 'The key must not contain "{}()/\\@:" (as mandated by PSR-6), got "{}()/\\@:"'],
            [true, 'The key must be a string, boolean given'],
            [false, 'The key must be a string, boolean given'],
            [123, 'The key must be a string, integer given'],
            [[], 'The key must be a string, array given'],
        ];
    }

    function provideBooleanResults(): array
    {
        return [
            [true],
            [false],
        ];
    }

    private function getNonexistentItemCallback(): callable
    {
        return function ($key, &$exists) {
            $exists = false;

            return null;
        };
    }
}
