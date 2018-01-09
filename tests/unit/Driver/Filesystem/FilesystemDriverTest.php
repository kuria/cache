<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem;

use Kuria\Cache\Driver\Filesystem\Entry\EntryFactoryInterface;
use Kuria\Cache\Driver\Filesystem\Entry\EntryInterface;
use Kuria\Cache\Test\IterableAssertionTrait;
use Kuria\Cache\Test\TimeMachine;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class FilesystemDriverTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var EntryFactoryInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $entryFactoryMock;
    /** @var EntryInterface[]|\PHPUnit_Framework_MockObject_MockObject[] */
    private $listedEntryMocks;
    /** @var FilesystemDriver */
    private $driver;
    /** @var int */
    private $entryMockSeq = 0;

    protected function setUp()
    {
        $this->entryFactoryMock = $this->createMock(EntryFactoryInterface::class);

        $this->listedEntryMocks = [
            'foo_valid' => $this->createEntryMock(true, 'foo_valid', serialize(1)),
            'bar_valid' =>$this->createEntryMock(true, 'bar_valid', serialize(2)),
            'baz_expired' => $this->createEntryMock(false, 'baz_expired', serialize(3)),
        ];

        $this->driver = $this->getMockBuilder(FilesystemDriver::class)
            ->setConstructorArgs(['/cache-path', $this->entryFactoryMock])
            ->setMethods(['listEntries', 'createCacheIterator'])
            ->getMock();

        $this->driver->method('listEntries')
            ->willReturn(array_values($this->listedEntryMocks));

        $this->driver->method('createCacheIterator')
            ->willReturn([]);
    }

    function testExists()
    {
        $this->prepareEntry('foo', true);
        $this->prepareEntry('bar', false);

        $this->assertTrue($this->driver->exists('foo'));
        $this->assertFalse($this->driver->exists('bar'));
    }

    function testRead()
    {
        $this->prepareEntry('key', true, serialize('value'));

        $this->assertSame('value', $this->driver->read('key'));
    }

    function testReadInvalid()
    {
        $this->prepareEntry('key', false);

        $this->assertNull($this->driver->read('key'));
    }

    function testWrite()
    {
        $entry = $this->prepareEntry('key', false);

        $entry->expects($this->once())
            ->method('write')
            ->with('key', serialize('value'), 0, false);

        $this->driver->write('key', 'value');
    }

    function testWriteWithTtlAndOverwrite()
    {
        TimeMachine::freezeTime([__NAMESPACE__], function (int $time) {
            $entry = $this->prepareEntry('key', false);

            $entry->expects($this->once())
                ->method('write')
                ->with('key', serialize('value'), $time + 60, true);

            $this->driver->write('key', 'value', 60, true);
        });
    }

    function testDelete()
    {
        $entry = $this->prepareEntry('key', true);

        $entry->expects($this->once())
            ->method('delete');

        $this->driver->delete('key');
    }

    function testClear()
    {
        foreach ($this->listedEntryMocks as $entryMock) {
            $entryMock->expects($this->once())
                ->method('delete');
        }

        $this->driver->clear();
    }

    function testCleanup()
    {
        foreach ($this->listedEntryMocks as $entryMock) {
            $entryMock->expects($this->once())
                ->method('validate');
        }

        $this->listedEntryMocks['foo_valid']->expects($this->never())
            ->method('delete');

        $this->listedEntryMocks['bar_valid']->expects($this->never())
            ->method('delete');

        $this->listedEntryMocks['baz_expired']->expects($this->once())
            ->method('delete');

        $this->driver->cleanup();
    }

    function testFilter()
    {
        foreach ($this->listedEntryMocks as $entryMock) {
            $entryMock->expects($this->once())
                ->method('validate');
        }

        $this->listedEntryMocks['foo_valid']->expects($this->once())
            ->method('delete');

        // filter() should delete invalid entries regardless of prefix
        $this->listedEntryMocks['baz_expired']->expects($this->once())
            ->method('delete');

        $this->driver->filter('foo_');
    }

    function testListKeys()
    {
        $this->assertSameIterable(['foo_valid', 'bar_valid'], $this->driver->listKeys());
        $this->assertSameIterable(['foo_valid'], $this->driver->listKeys('foo_'));
        $this->assertSameIterable(['bar_valid'], $this->driver->listKeys('bar_'));
        $this->assertSameIterable([], $this->driver->listKeys('baz_'));
    }

    /**
     * @return EntryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function prepareEntry(string $key, bool $valid, ?string $data = null)
    {
        $entryMock = $this->createEntryMock($valid, $valid ? $key : null, $data);

        $this->entryFactoryMock->expects($this->at($this->entryMockSeq++))
            ->method('fromKey')
            ->with('/cache-path', $key)
            ->willReturn($entryMock);

        return $entryMock;
    }

    /**
     * @return EntryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createEntryMock(bool $valid, ?string $key = null, ?string $data = null)
    {
        $entry = $this->createMock(EntryInterface::class);

        $entry->method('validate')->willReturn($valid);

        if ($key !== null) {
            $entry->method('readKey')->willReturn($key);
        }

        if ($data !== null) {
            $entry->method('readData')->willReturn($data);
        }

        return $entry;
    }
}

