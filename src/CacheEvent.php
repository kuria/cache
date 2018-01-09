<?php declare(strict_types=1);

namespace Kuria\Cache;

class CacheEvent
{
    /** @var string read-only */
    public $key;
    /** @var mixed */
    public $value;

    function __construct(string $key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
