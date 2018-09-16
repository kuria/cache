<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Kuria\Clock\Clock;
use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    /** @var string */
    private $key;

    /** @var mixed */
    private $value;

    /** @var bool */
    private $isHit;

    /** @var \DateTimeInterface|\DateInterval|int|null */
    private $expiration;

    /**
     * Internal constructor. Do not use.
     *
     * @internal
     * @see CacheItemPool::getItem()
     */
    function __construct(string $key, $value, bool $isHit)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    function getKey(): string
    {
        return $this->key;
    }

    function get()
    {
        return $this->value;
    }

    function isHit(): bool
    {
        return $this->isHit;
    }

    function set($value)
    {
        $this->value = $value;

        return $this;
    }

    function expiresAt($expiration)
    {
        $this->expiration = $expiration;

        return $this;
    }

    function expiresAfter($time)
    {
        $this->expiration = $time;

        return $this;
    }

    /**
     * Internal. Do not use.
     *
     * @internal
     */
    function getTtl(): ?int
    {
        if (is_int($this->expiration)) {
            return $this->expiration;
        }

        if ($this->expiration instanceof \DateTimeInterface) {
            return $this->expiration->getTimestamp() - Clock::time();
        }

        if ($this->expiration instanceof \DateInterval) {
            return PsrCacheHelper::convertDateIntervalToTtl($this->expiration);
        }

        return null;
    }
}
