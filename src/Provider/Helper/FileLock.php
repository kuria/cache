<?php

namespace Kuria\Cache\Provider\Helper;

/**
 * File lock
 *
 * Represents a single lock on a handle.
 *
 * The lock is automatically released on shutdown if
 * release() has not been called manually.
 *
 * @author ShiraNai7 <shira.cz>
 */
class FileLock
{
    /** @var array */
    protected static $registry = array();
    /** @var bool */
    protected static $shutdownFunctionRegistered = false;
    /** @var int */
    protected static $idCounter = 0;
    /** @var int */
    protected $id;
    /** @var resource */
    protected $handle;
    /** @var bool */
    protected $isExclusive;

    /**
     * @param resource $handle
     * @param bool     $isExclusive
     */
    protected function __construct($handle, $isExclusive)
    {
        $this->id = ++self::$idCounter;
        $this->handle = $handle;
        $this->isExclusive = $isExclusive;

        // add self to the registry
        self::$registry[$this->id] = $this;

        // register shutdown function on first use
        if (!self::$shutdownFunctionRegistered) {
            register_shutdown_function(array(__class__, 'releaseOnShutdown'));
            self::$shutdownFunctionRegistered = true;
        }
    }

    /**
     * Acquire exclusive file lock for the given handle
     *
     * @param resource $handle
     * @param bool     $blocking
     * @return static|bool false on failure
     */
    public static function acquireExclusive($handle, $blocking = true)
    {
        $operation = LOCK_EX;
        if (!$blocking) {
            $operation |= LOCK_NB;
        }

        if (flock($handle, $operation)) {
            return new static($handle, true);
        } else {
            return false;
        }
    }

    /**
     * Acquire shared file lock for the given handle
     *
     * @param resource $handle
     * @param bool     $blocking
     * @return static|bool false on failure
     */
    public static function acquireShared($handle, $blocking = true)
    {
        $operation = LOCK_SH;
        if (!$blocking) {
            $operation |= LOCK_NB;
        }

        if (flock($handle, $operation)) {
            return new static($handle, false);
        } else {
            return false;
        }
    }

    /**
     * See if the lock is exclusive
     *
     * @return bool
     */
    public function isExclusive()
    {
        return $this->isExclusive;
    }

    /**
     * Release the lock
     *
     * @return bool
     */
    public function release()
    {
        if (null !== $this->handle) {
            unset(self::$registry[$this->id]);

            if (flock($this->handle, LOCK_UN)) {
                $this->handle = null;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * See if the lock has been released
     *
     * @return bool
     */
    public function isReleased()
    {
        return null === $this->handle;
    }

    /**
     * Release all remaining locks on shutdown
     */
    public static function releaseOnShutdown()
    {
        foreach (self::$registry as $lock) {
            flock($lock->handle, LOCK_UN);
            $lock->handle = null;
        }

        self::$registry = array();
    }
}
