<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;
use Kuria\Clock\Clock;

/**
 * Uses flock() to handle concurrency. See warnings in PHP docs.
 */
class FlockEntry implements EntryInterface
{
    /** @var FileFormatInterface */
    private $format;

    /** @var string */
    private $path;

    /** @var FileHandle|null */
    private $handle;

    /** @var int */
    private $umask;

    function __construct(FileFormatInterface $format, string $path, int $umask)
    {
        $this->format = $format;
        $this->path = $path;
        $this->umask = $umask;
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

        return $expirationTime === 0 || $expirationTime > Clock::time();
    }

    function readKey(): string
    {
        return $this->format->readKey($this->requireHandle());
    }

    function readData()
    {
        return $this->format->readData($this->requireHandle());
    }

    function write(string $key, $data, int $expirationTime, bool $overwrite): void
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

    private function getHandle(bool $exclusive = false, bool $createFileIfNotExists = false): ?FileHandle
    {
        if ($this->handle === null) {
            // attempt to create new handle
            if (($this->handle = $this->createHandle($createFileIfNotExists)) === null) {
                return null;
            }

            /** @var FileHandle $handle */
            $handle = $this->handle;
        } else {
            // reuse existing handle
            /** @var FileHandle $handle */
            $handle = $this->handle;
            $handle->goto(0);
        }

        // acquire lock
        if (!$handle->isLocked() && !$handle->lock($exclusive)) {
            throw new EntryException(sprintf('Failed to acquire %s lock for "%s"', $exclusive ? 'exclusive' : 'shared', $this->path));
        }

        // upgrade lock to exclusive if needed
        if (
            $exclusive
            && $handle->isLocked()
            && !$handle->hasExclusiveLock()
            && !$handle->lock(true)
        ) {
            throw new EntryException(sprintf('Failed to upgrade shared lock on "%s" to exclusive', $this->path));
        }

        return $handle;
    }

    private function requireHandle(bool $exclusive = false, bool $createFileIfNotExists = false): FileHandle
    {
        $readHandle = $this->getHandle($exclusive, $createFileIfNotExists);

        if ($readHandle === null) {
            throw new EntryException(sprintf('Cannot get handle for "%s"', $this->path));
        }

        return $readHandle;
    }

    /**
     * @internal
     */
    protected function createHandle(bool $createFileIfNotExists): ?FileHandle
    {
        // make sure the target directory exists
        if ($createFileIfNotExists) {
            $targetDirectory = dirname($this->path);

            if (!is_dir($targetDirectory)) {
                @mkdir($targetDirectory, 0777 & ~$this->umask, true);
            }
        }

        // attempt to create handle
        $handle = @fopen($this->path, $createFileIfNotExists ? 'c+' : 'r+');

        if ($handle !== false) {
            @chmod($this->path, 0666 & ~$this->umask);

            return new FileHandle($this->path, $handle);
        }

        return null;
    }

    private function closeHandle(): void
    {
        if ($this->handle !== null) {
            $this->handle->close();
            $this->handle = null;
        }
    }
}
