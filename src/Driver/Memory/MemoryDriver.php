<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Memory;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Driver\Feature\CleanupInterface;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Helper\TtlHelper;
use Kuria\Clock\Clock;

class MemoryDriver implements DriverInterface, FilterableInterface, CleanupInterface, \Countable
{
    /** @var array[] */
    private $entries = [];

    function exists(string $key): bool
    {
        return $this->validate($key);
    }

    function read(string $key, &$exists = null)
    {
        if (!($exists = $this->validate($key))) {
            return null;
        }

        return $this->entries[$key]['value'];
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
        if (!$overwrite && $this->validate($key)) {
            throw new DriverException('A valid entry for this key already exists');
        }

        $entry = [
            'value' => $value,
            'expires' => TtlHelper::toExpirationTime($ttl),
        ];

        $this->entries[$key] = $entry;
    }

    function delete(string $key): void
    {
        if (!$this->validate($key)) {
            throw new DriverException('Failed to delete entry');
        }

        unset($this->entries[$key]);
    }

    function clear(): void
    {
        $this->entries = [];
    }

    function cleanup(): void
    {
        // listing all keys validates them all
        foreach ($this->listKeys() as $_);
    }

    function filter(string $prefix): void
    {
        foreach ($this->listKeys($prefix) as $key) {
            $this->delete($key);
        }
    }

    function listKeys(string $prefix = ''): iterable
    {
        $keys = array_keys($this->entries);
        $prefixLength = strlen($prefix);

        foreach ($keys as $key) {
            if ($this->validate($key) && ($prefixLength === 0 || strncmp($key, $prefix, $prefixLength) === 0)) {
                yield $key;
            }
        }
    }

    /**
     * Count all entries currently in the cache (including expired ones)
     */
    #[\ReturnTypeWillChange]
    function count()
    {
        return count($this->entries);
    }

    private function validate(string $key): bool
    {
        if (!isset($this->entries[$key])) {
            return false;
        }

        if ($this->entries[$key]['expires'] !== 0 && $this->entries[$key]['expires'] <= Clock::time()) {
            unset($this->entries[$key]);

            return false;
        }

        return true;
    }
}
