<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Apcu;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\DevMeta\Test;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group unit
 */
class ApcuDriverTest extends Test
{
    use PHPMock;

    /** @var ApcuDriver|MockObject */
    private $driver;

    protected function setUp()
    {
        $this->driver = $this->createPartialMock(ApcuDriver::class, ['createApcuIterator']);
    }

    function testShouldThrowExceptionIfApcuExtensionIsNotLoaded()
    {
        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('The apcu extension is not available');

        $this->getFunctionMock(__NAMESPACE__, 'extension_loaded')
            ->expects($this->once())
            ->with('apcu')
            ->willReturn(false);

        new ApcuDriver();
    }

    function testShouldCheckIfEntryExists()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_exists')
            ->expects($this->once())
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->driver->exists('key'));
    }

    function testShouldReadEntry()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->with('key')
            ->willReturnCallback(function ($key, &$success) {
                $success = true;

                return 'value';
            });

        $this->assertSame('value', $this->driver->read('key', $exists));
        $this->assertTrue($exists);
    }

    function testShouldHandleReadFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->with('key')
            ->willReturnCallback(function ($key, &$success) {
                $success = false;

                return 'unused';
            });

        $this->assertNull($this->driver->read('key', $exists));
        $this->assertFalse($exists);
    }

    function testShouldHandleReadException()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading the entry');

        $exists = 'initial';

        try {
            $this->driver->read('key', $exists);
        } finally {
            $this->assertSame('initial', $exists);
        }
    }

    function testShouldReadMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->with(['foo', 'baz'])
            ->willReturnCallback(function ($keys, &$success) {
                $success = true;

                return ['foo' => 'bar', 'baz' => 'qux'];
            });

        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], $this->driver->readMultiple(['foo', 'baz']));
    }

    function testShouldHandleReadMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->willReturnCallback(function ($keys, &$success) {
                $success = false;

                return false;
            });

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to fetch multiple entries');

        $this->driver->readMultiple(['foo', 'baz']);
    }

    function testShouldHandleReadMultipleException()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading multiple entries');

        $this->driver->readMultiple(['foo', 'baz']);
    }

    function testShouldWrite()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value');
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteWithTtl(?int $ttl, int $expectedTtlValue)
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with('key', 'value', $expectedTtlValue)
            ->willReturn(true);

        $this->driver->write('key', 'value', $ttl);
    }

    function testShouldHandleWriteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value');
    }

    function testShouldOverwrite()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value', null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteWithTtl(?int $ttl, int $expectedTtlValue)
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with('key', 'value', $expectedTtlValue)
            ->willReturn(true);

        $this->driver->write('key', 'value', $ttl, true);
    }

    function testShouldHandleOverwriteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value', null, true);
    }

    function testShouldWriteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 0)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteMultipleWithTtl(?int $ttl, int $expectedTtlValue)
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, $expectedTtlValue)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl);
    }

    function testShouldHandleWriteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->willReturn(['foo', 'baz']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entries: foo, baz');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function testShouldOverwriteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 0)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldOverwriteMultipleWithTtl(?int $ttl, int $expectedTtlValue)
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, $expectedTtlValue)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], $ttl, true);
    }

    function testShouldHandleOverwriteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->willReturn(['foo', 'baz']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entries: foo, baz');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    function provideTtl()
    {
        return [
            // ttl, expectedTtlValue
            [123, 123],
            [1, 1],
            [0, 0],
            [-1, 0],
            [null, 0],
        ];
    }

    function testShouldDelete()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with('key')
            ->willReturn(true);

        $this->driver->delete('key');
    }

    function testShouldHandleDeleteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testShouldDeleteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with(['foo', 'bar'])
            ->willReturn([]);

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testShouldHandleDeleteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(['bar']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entries: bar');

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testShouldClear()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_clear_cache')
            ->expects($this->once())
            ->willReturn(true);

        $this->driver->clear();
    }

    function testShouldFilter()
    {
        $this->mockApcuIterator('prefix_');

        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with($this->isInstanceOf(\APCuIterator::class))
            ->willReturn(true);

        $this->driver->filter('prefix_');
    }

    function testShouldHandleFilterFailure()
    {
        $this->mockApcuIterator('prefix_');

        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to filter entries');

        $this->driver->filter('prefix_');
    }

    function testShouldListKeys()
    {
        $this->mockApcuIterator('prefix_', ['prefix_foo', 'prefix_bar']);

        $this->assertSameIterable(['prefix_foo', 'prefix_bar'], $this->driver->listKeys('prefix_'));
    }

    private function mockApcuIterator(string $expectedPrefix, array $returnedKeys = []): void
    {
        $entryGenerator = function () use ($returnedKeys) {
            foreach ($returnedKeys as $key) {
                yield ['key' => $key];
            }
        };

        $apcuIterator = new class($entryGenerator()) extends \APCuIterator {
            private $iterator;

            function __construct(\Iterator $iterator)
            {
                parent::__construct();

                $this->iterator = $iterator;
            }

            function rewind()
            {
                $this->iterator->rewind();
            }

            function valid()
            {
                return $this->iterator->valid();
            }

            function current()
            {
                return $this->iterator->current();
            }

            function key()
            {
                return $this->iterator->key();
            }

            function next()
            {
                $this->iterator->next();
            }
        };

        $this->driver->expects($this->once())
            ->method('createApcuIterator')
            ->with($expectedPrefix)
            ->willReturn($apcuIterator);
    }
}
