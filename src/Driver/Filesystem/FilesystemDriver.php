<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Feature\CleanupInterface;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Filesystem\Entry\EntryFactory;
use Kuria\Cache\Driver\Filesystem\Entry\EntryFactoryInterface;
use Kuria\Cache\Driver\Filesystem\Entry\EntryInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\FlockEntryFactory;
use Kuria\Cache\Driver\Filesystem\PathResolver\PathResolverInterface;
use Kuria\Cache\Driver\Helper\TtlHelper;

class FilesystemDriver implements DriverInterface, CleanupInterface, FilterableInterface
{
    /** @var string */
    private $cachePath;

    /** @var EntryFactoryInterface */
    private $entryFactory;

    function __construct(string $cachePath, ?EntryFactoryInterface $entryFactory = null)
    {
        $this->cachePath = $cachePath;
        $this->entryFactory = $entryFactory ?? static::createEntryFactory();
    }

    /**
     * Create an entry factory suitable for the current environment
     */
    static function createEntryFactory(
        ?FileFormatInterface $fileFormat = null,
        ?PathResolverInterface $pathResolver = null,
        ?string $temporaryDirPath = null,
        ?int $umask = null
    ): EntryFactoryInterface {
        if (DIRECTORY_SEPARATOR === '\\') {
            // use file locking on Windows
            return new FlockEntryFactory($fileFormat, $pathResolver, $umask);
        }

        return new EntryFactory($fileFormat, $pathResolver, $temporaryDirPath, $umask);
    }

    function exists(string $key): bool
    {
        return $this->getEntryForKey($key)->validate();
    }

    function read(string $key, &$exists = null)
    {
        $entry = $this->getEntryForKey($key);

        if (!($exists = $entry->validate())) {
            return null;
        }

        $data = $entry->readData();
        $entry->close();

        return $data;
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
        $this->getEntryForKey($key)->write(
            $key,
            $value,
            TtlHelper::toExpirationTime($ttl),
            $overwrite
        );
    }

    function delete(string $key): void
    {
        $this->getEntryForKey($key)->delete();
    }

    function clear(): void
    {
        foreach ($this->listEntries() as $entry) {
            $entry->delete();
        }

        $this->cleanupEmptySubdirs();
    }

    function cleanup(): void
    {
        foreach ($this->listEntries() as $entry) {
            if (!$entry->validate()) {
                $entry->delete();
            }
        }

        $this->cleanupEmptySubdirs();
    }

    private function cleanupEmptySubdirs(): void
    {
        foreach ($this->createCacheIterator(false) as $path) {
            if (is_dir($path) && !(new \FilesystemIterator($path))->valid()) {
                @rmdir($path);
            }
        }
    }

    function filter(string $prefix): void
    {
        $prefixLength = strlen($prefix);

        foreach ($this->listEntries() as $entry) {
            if (
                !$entry->validate()
                || $prefixLength === 0
                || strncmp($prefix, $entry->readKey(), $prefixLength) === 0
            ) {
                $entry->delete();
            }
        }
    }

    function listKeys(string $prefix = ''): iterable
    {
        $prefixLength = strlen($prefix);

        foreach ($this->listEntries() as $entry) {
            if (
                $entry->validate()
                && (
                    $prefixLength === 0
                    || strncmp($prefix, $entry->readKey(), $prefixLength) === 0
                )
            ) {
                yield $entry->readKey();
            }
        }
    }

    private function getEntryForKey(string $key): EntryInterface
    {
        return $this->entryFactory->fromKey($this->cachePath, $key);
    }

    /**
     * @internal
     * @return EntryInterface[]
     */
    protected function listEntries(): iterable
    {
        foreach ($this->createCacheIterator() as $path) {
            yield $this->entryFactory->fromPath($path);
        }
    }

    /**
     * @internal
     * @return string[]
     */
    protected function createCacheIterator(bool $filesOnly = true): iterable
    {
        if (!is_dir($this->cachePath)) {
            return [];
        }

        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->cachePath,
                \RecursiveDirectoryIterator::CURRENT_AS_PATHNAME | \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            $filesOnly
                ? \RecursiveIteratorIterator::LEAVES_ONLY
                : \RecursiveIteratorIterator::CHILD_FIRST
        );
    }
}
