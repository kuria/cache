<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Apcu;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class ApcuDriverTest extends TestCase
{
    use IterableAssertionTrait;
    use PHPMock;

    /** @var ApcuDriver|MockObject */
    private $driver;

    protected function setUp()
    {
        $this->driver = $this->createPartialMock(ApcuDriver::class, ['createApcuIterator']);
    }

    function testExists()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_exists')
            ->expects($this->once())
            ->with('key')
            ->willReturn(true);

        $this->assertTrue($this->driver->exists('key'));
    }

    function testRead()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->with('key')
            ->willReturnCallback(function ($key, &$success) {
                $success = true;

                return 'value';
            });

        $this->assertSame('value', $this->driver->read('key'));
    }

    function testReadFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->with('key')
            ->willReturnCallback(function ($key, &$success) {
                $success = false;

                return 'unused';
            });

        $this->assertNull($this->driver->read('key'));
    }

    function testReadWithException()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading the entry');

        $this->driver->read('key');
    }

    function testReadMultiple()
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

    function testReadMultipleFailure()
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

    function testReadMultipleWithException()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_fetch')
            ->expects($this->once())
            ->willThrowException(new \Exception());

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('An exception was thrown when reading multiple entries');

        $this->driver->readMultiple(['foo', 'baz']);
    }

    function testWrite()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value');
    }

    function testWriteWithTtl()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with('key', 'value', 60)
            ->willReturn(true);

        $this->driver->write('key', 'value', 60);
    }

    function testWriteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value');
    }

    function testOverwrite()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with('key', 'value', 0)
            ->willReturn(true);

        $this->driver->write('key', 'value', null, true);
    }

    function testOverwriteWithTtl()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with('key', 'value', 60)
            ->willReturn(true);

        $this->driver->write('key', 'value', 60, true);
    }

    function testOverwriteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entry');

        $this->driver->write('key', 'value', null, true);
    }

    function testWriteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 0)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function testWriteMultipleWithTtl()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 60)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], 60);
    }

    function testWriteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_add')
            ->expects($this->once())
            ->willReturn(['foo', 'baz']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entries: foo, baz');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux']);
    }

    function testOverwriteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 0)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    function testOverwriteMultipleWithTtl()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->with(['foo' => 'bar', 'baz' => 'qux'], null, 60)
            ->willReturn([]);

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], 60, true);
    }

    function testOverwriteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_store')
            ->expects($this->once())
            ->willReturn(['foo', 'baz']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to write entries: foo, baz');

        $this->driver->writeMultiple(['foo' => 'bar', 'baz' => 'qux'], null, true);
    }

    function testDelete()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with('key')
            ->willReturn(true);

        $this->driver->delete('key');
    }

    function testDeleteFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entry');

        $this->driver->delete('key');
    }

    function testDeleteMultiple()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with(['foo', 'bar'])
            ->willReturn([]);

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testDeleteMultipleFailure()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(['bar']);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to delete entries: bar');

        $this->driver->deleteMultiple(['foo', 'bar']);
    }

    function testClear()
    {
        $this->getFunctionMock(__NAMESPACE__, 'apcu_clear_cache')
            ->expects($this->once())
            ->willReturn(true);

        $this->driver->clear();
    }

    function testFilter()
    {
        $this->mockApcuIterator('prefix_');

        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->with($this->isInstanceOf(\APCuIterator::class))
            ->willReturn(true);

        $this->driver->filter('prefix_');
    }

    function testFilterFailure()
    {
        $this->mockApcuIterator('prefix_');

        $this->getFunctionMock(__NAMESPACE__, 'apcu_delete')
            ->expects($this->once())
            ->willReturn(false);

        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Failed to filter entries');

        $this->driver->filter('prefix_');
    }

    function testListKeys()
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
