<?php

namespace Kuria\Cache\Extension\BoundFile;

use Kuria\Cache\Extension\CacheExtensionAbstract;

/**
 * Bound file extension
 *
 * Invalidates cache entries if any one of the bound files is modified.
 *
 * @author ShiraNai7 <shira.cz>
 */
class BoundFileExtension extends CacheExtensionAbstract
{
    protected $priorities = array(
        'wrap' => -1000,
        'unwrap' => 1000,
    );

    protected function getEvents()
    {
        return array(
            'store' => array('onStore', $this->priorities['wrap']),
            'fetch' => array('onFetch', $this->priorities['unwrap']),
        );
    }

    public function onStore(array $event)
    {
        if (isset($event['options']['bound_files'])) {
            $event['value'] = new FileBoundValue(
                $event['options']['bound_files'],
                $event['value']
            );
        }
    }

    public function onFetch(array $event)
    {
        if ($event['value'] instanceof FileBoundValue) {
            $event['value'] = $event['value']->validate()
                ? $event['value']->getWrappedValue()
                : false
            ;
        }
    }
}
