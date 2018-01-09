<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Redis;

use Kuria\Cache\Driver\DriverInterface;
use Kuria\Cache\Driver\Exception\DriverException;
use Kuria\Cache\Driver\Feature\FilterableInterface;
use Kuria\Cache\Driver\Feature\MultiDeleteInterface;
use Kuria\Cache\Driver\Feature\MultiReadInterface;
use Kuria\Cache\Driver\Feature\MultiWriteInterface;
use Kuria\Cache\Driver\Helper\SerializationHelper;
use Kuria\Cache\Helper\IterableHelper;

class RedisDriver implements DriverInterface, MultiReadInterface, MultiWriteInterface, MultiDeleteInterface, FilterableInterface
{
    /** @var \Redis */
    private $redis;

    /**
     * @param \Redis $redis
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    function exists(string $key): bool
    {
        return $this->redis->exists($key);
    }

    function read(string $key)
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return null;
        }

        return SerializationHelper::smartUnserialize($value);
    }

    function readMultiple(iterable $keys): iterable
    {
        $keys = IterableHelper::toArray($keys);
        $values = $this->redis->getMultiple($keys);

        foreach ($values as $index => $value) {
            if ($value === false) {
                continue;
            }

            yield $keys[$index] => SerializationHelper::smartUnserialize($value);
        }
    }

    function write(string $key, $value, ?int $ttl = null, bool $overwrite = false): void
    {
        if (!$this->redis->set($key, serialize($value), $this->getOptions($ttl, $overwrite))) {
            throw new DriverException('Failed to write entry');
        }
    }

    function writeMultiple(iterable $values, ?int $ttl = null, bool $overwrite = false): void
    {
        $options = $this->getOptions($ttl, $overwrite);

        $this->redis->multi();

        try {
            foreach ($values as $key => $value) {
                $this->redis->set($key, serialize($value), $options);
            }
        } catch (\Throwable $e) {
            $this->redis->discard();

            throw $e;
        }

        $failedResults = array_filter(
            $this->redis->exec(),
            function ($result) { return $result !== true; }
        );

        if ($failedResults) {
            throw new DriverException(sprintf('Failed to write entries at indexes: %s', implode(', ', array_keys($failedResults))));
        }
    }

    function delete(string $key): void
    {
        if ($this->redis->del($key) !== 1) {
            throw new DriverException('Failed to delete entry');
        }
    }

    function deleteMultiple(iterable $keys): void
    {
        $allKeys = IterableHelper::toArray($keys);

        $totalKeys = sizeof($allKeys);
        $numDeletedKeys = $this->redis->del(...$allKeys);

        if ($numDeletedKeys < $totalKeys) {
            throw new DriverException(sprintf('Failed to delete %d out of %d entries', $totalKeys - $numDeletedKeys, $totalKeys));
        }
    }

    function clear(): void
    {
        if (!$this->redis->flushDB()) {
            throw new DriverException('Failed to flush DB');
        }
    }

    function filter(string $prefix): void
    {
        $this->deleteMultiple($this->listKeys($prefix));
    }

    function listKeys(string $prefix = ''): iterable
    {
        return $this->redis->keys($this->escapePattern($prefix) . '*');
    }

    protected function getOptions(?int $ttl, bool $overwrite): array
    {
        $options = [];

        if ($ttl) {
            $options['ex'] = $ttl;
        }

        if (!$overwrite) {
            $options[] = 'nx';
        }

        return $options;
    }

    protected function escapePattern(string $pattern): string
    {
        return addcslashes($pattern, '?*[]^');
    }
}
