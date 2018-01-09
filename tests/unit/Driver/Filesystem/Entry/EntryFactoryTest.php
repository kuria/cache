<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\PathResolver\PathResolverInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class EntryFactoryTest extends TestCase
{
    /** @var FileFormatInterface */
    protected $fileFormat;
    /** @var PathResolverInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $pathResolverMock;
    /** @var EntryFactoryInterface */
    protected $factory;

    protected function setUp()
    {
        $this->fileFormat = $this->createMock(FileFormatInterface::class);
        $this->pathResolverMock = $this->createMock(PathResolverInterface::class);
        $this->factory = $this->createFactory();
    }

    protected function createFactory(): EntryFactoryInterface
    {
        return new EntryFactory($this->fileFormat, $this->pathResolverMock);
    }

    protected function getEntryImpl(): string
    {
        return Entry::class;
    }

    function testFromPath()
    {
        $path = __DIR__ . '/__DummyCachePath/entry';

        $entry = $this->factory->fromPath($path);

        $this->assertInstanceOf($this->getEntryImpl(), $entry);
        $this->assertSame($path, $entry->getPath());
    }

    function testFromKey()
    {
        $cachePath = __DIR__ . '/__DummyCachePath';

        $this->pathResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->identicalTo($this->fileFormat), 'foo.bar')
            ->willReturn('/resolved-entry');

        $entry = $this->factory->fromKey($cachePath, 'foo.bar');

        $this->assertInstanceOf($this->getEntryImpl(), $entry);
        $this->assertSame("{$cachePath}/resolved-entry", $entry->getPath());
    }
}
