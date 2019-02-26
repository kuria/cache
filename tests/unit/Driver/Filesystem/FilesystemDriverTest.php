<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem;

use Kuria\Cache\Driver\Filesystem\Entry\EntryFactoryInterface;
use Kuria\Cache\Driver\Filesystem\Entry\EntryInterface;
use Kuria\DevMeta\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group unit
 */
class FilesystemDriverTest extends Test
{
    /** @var EntryFactoryInterface|MockObject */
    private $entryFactoryMock;

    /** @var EntryInterface[]|MockObject[] */
    private $listedEntryMocks;

    /** @var FilesystemDriver */
    private $driver;

    /** @var int */
    private $entryMockSeq = 0;

    protected function setUp()
    {
        $this->entryFactoryMock = $this->createMock(EntryFactoryInterface::class);

        $this->listedEntryMocks = [
            'foo_valid' => $this->createEntryMock(true, 'foo_valid', 1),
            'bar_valid' =>$this->createEntryMock(true, 'bar_valid', 2),
            'baz_expired' => $this->createEntryMock(false, 'baz_expired', 3),
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

    function testShouldCheckIfEntryExists()
    {
        $this->prepareEntry('foo', true);
        $this->prepareEntry('bar', false);

        $this->assertTrue($this->driver->exists('foo'));
        $this->assertFalse($this->driver->exists('bar'));
    }

    function testShouldRead()
    {
        $this->prepareEntry('key', true, 'value');

        $this->assertSame('value', $this->driver->read('key', $exists));
        $this->assertTrue($exists);
    }

    function testShouldReadInvalid()
    {
        $this->prepareEntry('key', false);

        $this->assertNull($this->driver->read('key', $exists));
        $this->assertFalse($exists);
    }

    function testShouldWrite()
    {
        $entry = $this->prepareEntry('key', false);

        $entry->expects($this->once())
            ->method('write')
            ->with('key', 'value', 0, false);

        $this->driver->write('key', 'value');
    }

    /**
     * @dataProvider provideTtl
     */
    function testShouldWriteWithTtlAndOverwrite(?int $ttl, int $now, int $expectedExpirationTime)
    {
        $this->atTime($now, function () use ($ttl, $expectedExpirationTime) {
            $entry = $this->prepareEntry('key', false);

            $entry->expects($this->once())
                ->method('write')
                ->with('key', 'value', $expectedExpirationTime, true);

            $this->driver->write('key', 'value', $ttl, true);
        });
    }

    function provideTtl()
    {
        return [
            // ttl, now, expectedExpirationTime
            [1, 123, 124],
            [60, 1000, 1060],
            [null, 123, 0],
            [0, 123, 0],
            [-1, 123, 0],
        ];
    }

    function testShouldDelete()
    {
        $entry = $this->prepareEntry('key', true);

        $entry->expects($this->once())
            ->method('delete');

        $this->driver->delete('key');
    }

    function testShouldClear()
    {
        foreach ($this->listedEntryMocks as $entryMock) {
            $entryMock->expects($this->once())
                ->method('delete');
        }

        $this->driver->clear();
    }

    function testShouldCleanup()
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

    function testShouldFilter()
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

    function testShouldListKeys()
    {
        $this->assertSameIterable(['foo_valid', 'bar_valid'], $this->driver->listKeys());
        $this->assertSameIterable(['foo_valid'], $this->driver->listKeys('foo_'));
        $this->assertSameIterable(['bar_valid'], $this->driver->listKeys('bar_'));
        $this->assertSameIterable([], $this->driver->listKeys('baz_'));
    }

    /**
     * @return EntryInterface|MockObject
     */
    private function prepareEntry(string $key, bool $valid, $data = null)
    {
        $entryMock = $this->createEntryMock($valid, $valid ? $key : null, $data);

        $this->entryFactoryMock->expects($this->at($this->entryMockSeq++))
            ->method('fromKey')
            ->with('/cache-path', $key)
            ->willReturn($entryMock);

        return $entryMock;
    }

    /**
     * @return EntryInterface|MockObject
     */
    private function createEntryMock(bool $valid, ?string $key = null, $data = null)
    {
        $entryMock = $this->createMock(EntryInterface::class);

        $entryMock->method('validate')->willReturn($valid);

        if ($key !== null) {
            $entryMock->method('readKey')->willReturn($key);
        }

        if ($data !== null) {
            $entryMock->method('readData')->willReturn($data);
        }

        return $entryMock;
    }
}
