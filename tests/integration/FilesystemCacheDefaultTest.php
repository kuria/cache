<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Filesystem\FilesystemDriver;

/**
 * @group integration
 */
class FilesystemCacheDefaultTest extends CacheTest
{
    protected const CACHE_PATH = __DIR__ . '/../temp.filesystem.cache';

    protected function createDriver(): DriverInterface
    {
        return new FilesystemDriver(static::CACHE_PATH);
    }

    function testClearShouldRemoveEmptyDirectories()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('baz', 'qux');
        $this->cache->clear();

        $this->assertFalse((new \FilesystemIterator(static::CACHE_PATH))->valid());
    }

    function testCleanupShouldRemoveEmptyDirectories()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('baz', 'qux');
        $this->cache->deleteMultiple(['foo', 'baz']);
        $this->cache->cleanup();

        $this->assertFalse((new \FilesystemIterator(static::CACHE_PATH))->valid());
    }

    function testListingEntriesWithNoCacheDir()
    {
        $this->cache->cleanup();
        rmdir(static::CACHE_PATH);

        $this->assertDirectoryNotExists(static::CACHE_PATH);
        $this->assertSameIterable([], $this->cache->listKeys());
    }
}
