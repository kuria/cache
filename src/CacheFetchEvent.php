<?php

namespace Kuria\Cache;

use Kuria\Event\Event;

/**
 * Cache fetch event
 *
 * @author ShiraNai7 <shira.cz>
 */
class CacheFetchEvent extends Event
{
    const NAME = 'kuria.cache.fetch';

    /** @var string */
    public $category;
    /** @var string */
    public $entry;
    /** @var mixed */
    public $data;
    /** @var array */
    public $options;

    /**
     * @param string $category
     * @param string $entry
     * @param mixed  &$data
     * @param array  $options
     */
    public function __construct($category, $entry, &$data, array $options)
    {
        $this->name = self::NAME;
        $this->category = $category;
        $this->entry = $entry;
        $this->data = &$data;
        $this->options = $options;
    }
}
