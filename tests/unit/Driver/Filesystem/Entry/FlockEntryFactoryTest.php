<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

/**
 * @group unit
 */
class FlockEntryFactoryTest extends EntryFactoryTest
{
    protected function createFactory(): EntryFactoryInterface
    {
        return new FlockEntryFactory($this->fileFormatMock, $this->pathResolverMock);
    }

    protected function getEntryImpl(): string
    {
        return FlockEntry::class;
    }
}
