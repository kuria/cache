<?php declare(strict_types=1);

namespace Kuria\Cache;

/**
 * Common cache prefix handling logic
 */
trait CachePrefixTrait
{
    /** @var string */
    private $prefix = '';
    /** @var int|null */
    private $prefixLength;

    function getPrefix(): string
    {
        return $this->prefix;
    }

    function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        $this->prefixLength = null;
    }

    private function applyPrefix(string $to): string
    {
        return $this->prefix !== '' ? $this->prefix . $to : $to;
    }

    private function applyPrefixToValues(iterable $to): \Iterator
    {
        if ($this->prefix === '') {
            yield from $to;

            return;
        }

        foreach ($to as $key => $value) {
            yield $key => $this->prefix . $value;
        }
    }

    private function applyPrefixToKeys(iterable $iterable): \Iterator
    {
        if ($this->prefix === '') {
            yield from $iterable;

            return;
        }

        foreach ($iterable as $key => $value) {
            yield $this->prefix . $key => $value;
        }
    }

    private function stripPrefix(string $prefixed): string
    {
        if ($this->prefix === '') {
            return $prefixed;
        }

        return substr($prefixed, $this->prefixLength ?? ($this->prefixLength = strlen($this->prefix)));
    }
}
