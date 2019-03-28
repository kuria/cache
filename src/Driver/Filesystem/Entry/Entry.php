<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\EntryException;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileHandle;
use Kuria\Clock\Clock;

/**
 * Default entry implementation
 *
 * Uses temporary files to atomically write and replace entries.
 *
 * Warning: Does not support concurrency on Windows.
 *
 * @see FlockEntry
 */
class Entry implements EntryInterface
{
    /** @var FileFormatInterface */
    private $format;

    /** @var string */
    private $path;

    /** @var string */
    private $temporaryDirPath;

    /** @var int */
    private $umask;

    /** @var FileHandle|null */
    private $readHandle;

    function __construct(FileFormatInterface $format, string $path, string $temporaryDirPath, int $umask)
    {
        $this->format = $format;
        $this->path = $path;
        $this->temporaryDirPath = $temporaryDirPath;
        $this->umask = $umask;
    }

    function getPath(): string
    {
        return $this->path;
    }

    function validate(): bool
    {
        $handle = $this->getReadHandle();

        if ($handle === null || !$this->format->validate($handle)) {
            return false;
        }

        $expirationTime = $this->format->readExpirationTime($handle);

        return $expirationTime === 0 || $expirationTime > Clock::time();
    }

    function readKey(): string
    {
        return $this->format->readKey($this->requireReadHandle());
    }

    function readData()
    {
        return $this->format->readData($this->requireReadHandle());
    }

    function write(string $key, $data, int $expirationTime, bool $overwrite): void
    {
        if (!$overwrite && $this->validate()) {
            throw new EntryException(sprintf('Entry "%s" already exists and is valid', $this->path));
        }

        $this->closeReadHandle();

        $tmpFile = $this->writeToTemporaryFile($key, $data, $expirationTime);

        try {
            $this->replaceFile($tmpFile);
        } catch (\Throwable $e) {
            @unlink($tmpFile);

            throw $e;
        }
    }

    /**
     * @internal
     */
    protected function writeToTemporaryFile(string $key, $data, int $expirationTime): string
    {
        // get a temporary file name
        $tmpPath = @tempnam($this->temporaryDirPath, 'cache');

        if (!is_string($tmpPath)) {
            throw new EntryException('Failed to generate temporary path using tempnam()');
        }

        // open the temporary file for writing
        $tmpHandle = @fopen($tmpPath, 'w');

        if ($tmpHandle === false) {
            throw new EntryException(sprintf('Failed to open temporary file "%s" for writing', $tmpPath));
        }

        // write
        $writeHandle = new FileHandle($tmpPath, $tmpHandle);

        try {
            $this->format->write($writeHandle, $key, $data, $expirationTime);
        } catch (\Throwable $e) {
            @unlink($tmpPath);

            throw $e;
        } finally {
            $writeHandle->close();
        }

        return $tmpPath;
    }

    /**
     * @internal
     */
    protected function replaceFile(string $with): void
    {
        // make sure the target directory exists
        $targetDirectory = dirname($this->path);

        if (!is_dir($targetDirectory)) {
            @mkdir($targetDirectory, 0777 & ~$this->umask, true);
        }

        // set permissions
        @chmod($with, 0666 & ~$this->umask);

        // move the file
        if (@rename($with, $this->path) !== true) {
            throw new EntryException(sprintf('Failed to rename file "%s" to "%s"', $with, $this->path));
        }
    }

    function delete(): void
    {
        $this->closeReadHandle();

        if (!@unlink($this->path)) {
            throw new EntryException(sprintf('Failed to delete file "%s"', $this->path));
        }
    }

    function close(): void
    {
        $this->closeReadHandle();
    }

    private function getReadHandle(): ?FileHandle
    {
        if ($this->readHandle === null) {
            // create new handle
            $this->readHandle = $this->createReadHandle();
        } else {
            // reuse existing handle
            $this->readHandle->goto(0);
        }

        return $this->readHandle;
    }

    private function requireReadHandle(): FileHandle
    {
        $readHandle = $this->getReadHandle();

        if ($readHandle === null) {
            throw new EntryException(sprintf('Cannot get read handle for "%s"', $this->path));
        }

        return $readHandle;
    }

    /**
     * @internal
     */
    protected function createReadHandle(): ?FileHandle
    {
        $handle = @fopen($this->path, 'r');

        return $handle !== false ? new FileHandle($this->path, $handle) : null;
    }

    private function closeReadHandle(): void
    {
        if ($this->readHandle) {
            $this->readHandle->close();
            $this->readHandle = null;
        }
    }
}
