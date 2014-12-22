<?php

namespace Kuria\Cache;

use Kuria\Event\Event;

/**
 * Cache store event
 *
 * @author ShiraNai7 <shira.cz>
 */
class CacheStoreEvent extends Event
{
    const NAME = 'kuria.cache.store';

    /** @var string */
    public $category;
    /** @var string */
    public $entry;
    /** @var mixed */
    public $data;
    /** @var int */
    public $ttl;
    /** @var array */
    public $options;

    /**
     * @param string $category
     * @param string $entry
     * @param mixed  &$data
     * @param int    &$ttl
     * @param array  $options
     */
    public function __construct($category, $entry, &$data, &$ttl, array $options)
    {
        $this->name = self::NAME;
        $this->category = $category;
        $this->entry = $entry;
        $this->data = &$data;
        $this->ttl = &$ttl;
        $this->options = $options;
    }
}
