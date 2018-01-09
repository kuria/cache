<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;

/**
 * Uses flock() to handle concurrency. See warnings in PHP docs.
 */
class FlockEntry implements EntryInterface
{
    /** @var FileFormatInterface */
    protected $format;
    /** @var string */
    protected $path;
    /** @var FileHandle|null */
    protected $handle;

    function __construct(FileFormatInterface $format, string $path)
    {
        $this->format = $format;
        $this->path = $path;
    }

    function getPath(): string
    {
        return $this->path;
    }

    function validate(): bool
    {
        $handle = $this->getHandle();

        if ($handle === null || !$this->format->validate($handle)) {
            return false;
        }

        $expirationTime = $this->format->readExpirationTime($handle);

        return $expirationTime === 0 || $expirationTime > time();
    }

    function readKey(): string
    {
        return $this->format->readKey($this->requireHandle());
    }

    function readData(): string
    {
        return $this->format->readData($this->requireHandle());
    }

    function write(string $key, string $data, int $expirationTime, bool $overwrite): void
    {
        $handle = $this->requireHandle(true, true);

        if (!$overwrite && $this->validate()) {
            throw new EntryException(sprintf('Entry "%s" already exists and is valid', $this->path));
        }

        $handle->truncate();

        $this->format->write($handle, $key, $data, $expirationTime);
    }

    function delete(): void
    {
        $this->closeHandle();

        // attempt to unlink the file
        // this may fail on Windows if the file is currently open
        if (@unlink($this->path)) {
            return;
        }

        // fallback to truncating the file
        $handle = $this->getHandle(true);

        if ($handle === null) {
            throw new EntryException(sprintf('Failed to delete entry "%s" - does not exist', $this->path));
        }

        $handle->truncate();

        $this->closeHandle();
    }

    function close(): void
    {
        $this->closeHandle();
    }

    protected function getHandle(bool $exclusive = false, bool $createFileIfNotExists = false): ?FileHandle
    {
        if ($this->handle === null) {
            // attempt to create new handle
            if (($this->handle = $this->createHandle($createFileIfNotExists)) === null) {
                return null;
            }
        } else {
            // reuse existing handle
            $this->handle->goto(0);
        }

        // acquire lock
        if (!$this->handle->isLocked() && !$this->handle->lock($exclusive)) {
            throw new EntryException(sprintf('Failed to acquire %s lock for "%s"', $exclusive ? 'exclusive' : 'shared', $this->path));
        }

        // upgrade lock to exclusive if needed
        if (
            $exclusive
            && $this->handle->isLocked()
            && !$this->handle->hasExclusiveLock()
            && !$this->handle->lock(true)
        ) {
            throw new EntryException(sprintf('Failed to upgrade shared lock on "%s" to exclusive', $this->path));
        }

        return $this->handle;
    }

    protected function requireHandle(bool $exclusive = false, bool $createFileIfNotExists = false): FileHandle
    {
        $readHandle = $this->getHandle($exclusive, $createFileIfNotExists);

        if ($readHandle === null) {
            throw new EntryException(sprintf('Cannot get handle for "%s"', $this->path));
        }

        return $readHandle;
    }

    protected function createHandle(bool $createFileIfNotExists): ?FileHandle
    {
        // make sure the target directory exists
        if ($createFileIfNotExists) {
            $targetDirectory = dirname($this->path);

            if (!is_dir($targetDirectory)) {
                @mkdir($targetDirectory, 0777 & ~umask(), true);
            }
        }

        // attempt to create handle
        $handle = @fopen($this->path, $createFileIfNotExists ? 'c+' : 'r+');

        return $handle !== false ? new FileHandle($handle) : null;
    }

    protected function closeHandle(): void
    {
        if ($this->handle !== null) {
            $this->handle->close();
            $this->handle = null;
        }
    }
}