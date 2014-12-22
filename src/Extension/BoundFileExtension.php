<?php

namespace Kuria\Cache\Extension;

use Kuria\Cache\CacheFetchEvent;
use Kuria\Cache\CacheStoreEvent;
use Kuria\Event\EventSubscriberInterface;

/**
 * Bound file extension
 *
 * Invalidates cache entries based on modification time of a given list of files.
 *
 * Basic usage:
 *
 *      $cache->addSubscriber(new BoundFileExtension());
 *
 * @author ShiraNai7 <shira.cz>
 */
class BoundFileExtension implements EventSubscriberInterface
{
    /** @var bool */
    protected $verifyBoundFiles = true;
    /** @var bool */
    protected $alwaysMapBoundFiles = false;

    public function getEvents()
    {
        return array(
            CacheFetchEvent::NAME => 'onFetch',
            CacheStoreEvent::NAME => 'onStore',
        );
    }

    /**
     * Enable or disable verification of bound files
     *
     * @param bool $verifyBoundFiles
     */
    public function setVerifyBoundFiles($verifyBoundFiles)
    {
        $this->verifyBoundFiles = $verifyBoundFiles;
    }

    /**
     * Set whether bound files should be mapped even
     * if the verification is disabled at the moment of storing.
     *
     * @param bool $alwaysMapBoundFiles
     */
    public function setAlwaysMapBoundFiles($alwaysMapBoundFiles)
    {
        $this->alwaysMapBoundFiles = $alwaysMapBoundFiles;
    }

    /**
     * Handle retrieving data from the cache
     *
     * @param CacheFetchEvent $event
     */
    public function onFetch(CacheFetchEvent $event)
    {
        if (
            isset($event->options['has_bound_files'])
            && $event->options['has_bound_files']
        ) {
            if (
                is_array($event->data)
                && array_key_exists('__data', $event->data)
                && array_key_exists('__bound_files', $event->data)
                && (
                    !$this->verifyBoundFiles
                    || $this->verifyBoundFileMap($event->data['__bound_files'])
                )
            ) {
                // ok
                $event->data = $event->data['__data'];
            } else {
                // invalid data or a bound file has been modified
                $event->data = false;
            }
        }
    }

    /**
     * Handle storing data into the cache
     *
     * @param CacheStoreEvent $event
     */
    public function onStore(CacheStoreEvent $event)
    {
        if (isset($event->options['bound_files'])) {
            $event->data = array(
                '__data' => $event->data,
                '__bound_files' => $this->verifyBoundFiles || $this->alwaysMapBoundFiles
                    ? $this->createBoundFileMap($event->options['bound_files'])
                    : array(),
            );
        }
    }

    /**
     * Create map of bound files
     *
     * @param string[] $boundFiles
     * @return array
     */
    protected function createBoundFileMap(array $boundFiles)
    {
        $boundFileMap = array();
        foreach ($boundFiles as $boundFile) {
            if (!is_file($boundFile)) {
                throw new \InvalidArgumentException(sprintf('Invalid bound file "%s"', $boundFile));
            }

            $boundFileMap[realpath($boundFile)] = filemtime($boundFile);
        }

        return $boundFileMap;
    }

    /**
     * Verify map of bound files
     *
     * @param array $boundFileMap
     * @return bool
     */
    protected function verifyBoundFileMap(array $boundFileMap)
    {
        foreach ($boundFileMap as $boundFile => $lastKnownMTime) {
            if (@filemtime($boundFile) !== $lastKnownMTime) {
                return false;
            }
        }

        return true;
    }
}
