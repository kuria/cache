<?php

namespace Kuria\Cache\Extension;

use Kuria\Event\EventSubscriberAbstract;

abstract class CacheExtensionAbstract extends EventSubscriberAbstract
{
    /** @var int */
    protected $priority = 0;

    /**
     * @param int $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }
}
