<?php

namespace Kuria\Cache\Provider\Helper;

/**
 * Temporary file
 *
 * An extension of SplFileInfo designed to deal with temporary files.
 * The temporary file is always removed at the end of the request,
 * even if the script ends with an uncaught exception or a fatal error.
 *
 * @author ShiraNai7 <shira.cz>
 */
class TemporaryFile extends \SplFileInfo
{
    /** @var array */
    protected static $registry = array();
    /** @var bool */
    protected static $shutdownFunctionRegistered = false;
    /** @var bool */
    protected $discarded = false;

    /**
     * @param string|null $fileName existing file name or null to generate
     * @param string|null $tmpDir   existing temporary directory or null to use the system's default
     */
    public function __construct($fileName = null, $tmpDir = null)
    {
        if (null === $fileName) {
            // create temporary file
            $fileName = tempnam($tmpDir ?: sys_get_temp_dir(), '');
            if (false === $fileName) {
                throw new \RuntimeException('Unable to create temporary file');
            }
        }

        // register shutdown function on first use
        if (!self::$shutdownFunctionRegistered) {
            register_shutdown_function(array(__class__, 'cleanupOnShutdown'));
            self::$shutdownFunctionRegistered = true;
        }

        // call parent constructor
        parent::__construct($fileName);

        // add file name to registry
        self::$registry[$this->getRealPath()] = true;
    }

    /**
     * Discard the temporary file immediately
     *
     * @return bool
     */
    public function discard()
    {
        if (!$this->discarded) {
            if (is_file($realPath = $this->getRealPath())) {
                $success = @unlink($realPath);
            } else {
                $success = true;
            }
            if ($success) {
                unset(self::$registry[$realPath]);
                $this->discarded = true;
            }

            return $success;
        }

        return true;
    }

    /**
     * Keep the file
     * Removes the file from the cleanup-on-shutdown registry.
     */
    public function keep()
    {
        unset(self::$registry[$this->getRealPath()]);
    }

    /**
     * Clean-up temporary files on shutdown
     */
    public static function cleanupOnShutdown()
    {
        foreach (array_keys(self::$registry) as $realPath) {
            if (is_file($realPath)) {
                @unlink($realPath);
            }
        }

        self::$registry = array();
    }
}
