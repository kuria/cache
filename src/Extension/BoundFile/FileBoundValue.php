<?php

namespace Kuria\Cache\Extension\BoundFile;

use Kuria\Cache\WrappedCachedValueInterface;

/**
 * File bound cached value
 *
 * Wraps a cached value, storing a map of bound files along with it.
 *
 * @author ShiraNai7 <shira.cz>
 */
class FileBoundValue implements WrappedCachedValueInterface
{
    /** @var int[] realpath => filemtime */
    protected $boundFileMap;
    /** @var mixed */
    protected $wrappedValue;
    /** @var bool|null */
    protected $isValid;

    /**
     * @param string[] $boundFiles
     * @param mixed    $wrappedValue
     */
    public function __construct(array $boundFiles, $wrappedValue)
    {
        $this->boundFileMap = $this->createBoundFileMap($boundFiles);
        $this->wrappedValue = $wrappedValue;
    }

    public function __sleep()
    {
        return array('boundFileMap', 'wrappedValue');
    }

    /**
     * See if any bound files have changed since the value has been wrapped
     *
     *  - the result of this method is cached for the lifetime of this instance
     *  - serializing this instance discards the cached result
     *
     * @param bool $bypassCache bypass the internal and PHP's stat cache 1/0
     * @return bool true if no bound files have changed, false otherwise
     */
    public function validate($bypassCache = false)
    {
        if (null === $this->isValid || $bypassCache) {
            $this->isValid = $this->verifyBoundFileMap($this->boundFileMap, $bypassCache);
        }

        return $this->isValid;
    }

    /**
     * @return mixed
     */
    public function getWrappedValue()
    {
        return $this->wrappedValue;
    }

    /**
     * @return int[] realpath => filemtime
     */
    public function getBoundFileMap()
    {
        return $this->boundFileMap;
    }

    /**
     * Create map of bound files
     *
     * @param string[] $boundFiles
     * @throws \InvalidArgumentException if any one of the files does not exist
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
     * Verify a map of bound files
     *
     * @param array $boundFileMap
     * @param bool  $bypassStatCache
     * @return bool
     */
    protected function verifyBoundFileMap(array $boundFileMap, $bypassStatCache = false)
    {
        foreach ($boundFileMap as $boundFile => $lastKnownMTime) {
            if ($bypassStatCache) {
                clearstatcache(true, $boundFile);
            }
            if (@filemtime($boundFile) !== $lastKnownMTime) {
                return false;
            }
        }

        return true;
    }
}
