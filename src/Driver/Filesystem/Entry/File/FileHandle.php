<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry\File;

use Kuria\Cache\Driver\Filesystem\Entry\Exception\FileHandleException;

class FileHandle
{
    /** @var string */
    private $path;

    /** @var resource */
    private $handle;

    /** @var bool */
    private $locked = false;

    /** @var bool */
    private $exclusiveLock = false;

    /**
     * @param resource $handle
     */
    function __construct(string $path, $handle)
    {
        $this->path = $path;
        $this->handle = $handle;
    }

    function __destruct()
    {
        if ($this->locked) {
            $this->unlock();
        }
    }

    function getPath(): string
    {
        return $this->path;
    }

    function getSize(): int
    {
        $stat = @fstat($this->handle);

        if ($stat === false) {
            throw new FileHandleException(sprintf('Failed to stat "%s"', $this->path));
        }

        return $stat['size'];
    }

    function getPosition(): int
    {
        $position = @ftell($this->handle);

        if ($position === false) {
            throw new FileHandleException(sprintf('Failed to get handle position of "%s"', $this->path));
        }

        return $position;
    }

    function getRemaningBytes(): int
    {
        return max($this->getSize() - $this->getPosition(), 0);
    }

    function goto(int $position): void
    {
        if (@fseek($this->handle, $position) !== 0) {
            throw new FileHandleException(sprintf('Failed to seek handle of "%s"', $this->path));
        }
    }

    function move(int $offset): void
    {
        if (@fseek($this->handle, $offset, SEEK_CUR) !== 0) {
            throw new FileHandleException(sprintf('Failed to seek handle of "%s"', $this->path));
        }
    }

    function skipInt(): void
    {
        $this->move($this->getIntSize());
    }

    function readInt(): int
    {
        $data = @unpack($this->getIntPackFormat(), (string) fread($this->handle, $this->getIntSize()));

        if (!is_array($data)) {
            throw new FileHandleException(sprintf('Failed to read integer data from "%s"', $this->path));
        }

        return $data[1];
    }

    function writeInt(int $value): void
    {
        if (@fwrite($this->handle, pack($this->getIntPackFormat(), $value)) !== $this->getIntSize()) {
            throw new FileHandleException(sprintf('Failed to write integer data to "%s"', $this->path));
        }
    }

    function getIntSize(): int
    {
        return PHP_INT_SIZE >= 8 ? 8 : 4;
    }

    function readString(?int $length = null): string
    {
        if ($length === 0) {
            return '';
        }

        $data = @stream_get_contents($this->handle, $length ?? -1);

        if ($data === false || $length !== null && strlen($data) !== $length) {
            throw new FileHandleException(sprintf('Failed to read string data from "%s"', $this->path));
        }

        return $data;
    }

    function writeString(string $value): void
    {
        if (@fwrite($this->handle, $value) !== strlen($value)) {
            throw new FileHandleException(sprintf('Failed to write string data to "%s"', $this->path));
        }
    }

    function truncate(int $size = 0): void
    {
        if (@ftruncate($this->handle, $size) !== true) {
            throw new FileHandleException(sprintf('Failed to truncate "%s" to %d bytes', $this->path, $size));
        }
    }

    function close(): void
    {
        if ($this->locked) {
            $this->unlock();
        }

        @fclose($this->handle);
    }

    function lock(bool $exclusive = false, bool $block = true): bool
    {
        $operation = $exclusive ? LOCK_EX : LOCK_SH;

        if (!$block) {
            $operation |= LOCK_NB;
        }

        if (flock($this->handle, $operation)) {
            $this->locked = true;
            $this->exclusiveLock = $exclusive;

            return true;
        } else {
            $this->locked = false;
            $this->exclusiveLock = false;

            return false;
        }
    }

    function unlock(): bool
    {
        if (flock($this->handle, LOCK_UN)) {
            $this->locked = false;
            $this->exclusiveLock = false;

            return true;
        }

        return false;
    }

    function isLocked(): bool
    {
        return $this->locked;
    }

    function hasExclusiveLock(): bool
    {
        return $this->exclusiveLock;
    }

    private function getIntPackFormat(): string
    {
        return PHP_INT_SIZE >= 8 ? 'J' : 'M';
    }
}
