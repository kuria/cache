<?php

namespace Kuria\Cache\Extension;

use Kuria\Event\EventSubscriber;

/**
 * Optional base class for cache extensions
 *
 * Makes priorities configurable (for interoperability).
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class CacheExtension extends EventSubscriber
{
    /** @var int[] key => priority map (set by subclass) */
    protected $priorities;

    /**
     * Set priority for the given action
     *
     * @param string $key
     * @param int    $priority
     * @throws \OutOfBoundsException if the key is invalid
     * @return static
     */
    public function setPriority($key, $priority)
    {
        if (!isset($this->priorities[$key])) {
            throw new \OutOfBoundsException(sprintf(
                'Unknown priority key "%s", valid keys are: %s',
                $key,
                implode(', ', array_keys($this->priorities))
            ));
        }

        $this->priorities[$key] = $priority;

        return $this;
    }
}