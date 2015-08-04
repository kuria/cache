<?php

namespace Kuria\Cache\Driver;

use Kuria\Cache\Util\TemporaryFile;

/**
 * Filesystem cache driver
 *
 * @author ShiraNai7 <shira.cz>
 */
class FilesystemDriver implements DriverInterface, FilterableInterface
{
    /** Storage mode - .dat files that MUST NOT be publicly accessible */
    const STORAGE_NORMAL = 0;
    /** Storage mode - .php files that are safe to be in a public directory */
    const STORAGE_PHP = 1;

    /** @var string */
    protected $cacheDir;
    /** @var int */
    protected $storageMode = self::STORAGE_PHP;
    /** @var string|null */
    protected $temporaryDir;
    /** @var bool */
    protected $useTemporaryFiles = false;
    /** @var bool */
    protected $useUnlink = false;

    /**
     * @param string      $cacheDir     path to the cache directory, without trailing slash
     * @param string|null $temporaryDir path to the temporary directory, without trailing slash
     */
    public function __construct($cacheDir, $temporaryDir = null)
    {
        $this
            ->setCacheDir($cacheDir)
            ->setTemporaryDir($temporaryDir)
        ;

        $isWindows = '\\' === DIRECTORY_SEPARATOR;

        $this->useTemporaryFiles = !$isWindows;
        $this->useUnlink = !$isWindows;
    }

    /**
     * Set cache directory
     *
     * If it does not exist, it is created.
     *
     * @param string $cacheDir path to the cache directory, without trailing slash
     * @throws \RuntimeException         if the directory could not be created
     * @throws \InvalidArgumentException if the directory is a filesystem root
     * @return FilesystemDriver
     */
    public function setCacheDir($cacheDir)
    {
        // create cache directory if it does not exist
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true)) {
            throw new \RuntimeException(sprintf('Could not create cache directory "%s"', $cacheDir));
        }

        // make sure the cache directory is not set to a filesystem root
        $cacheDirRealPath = realpath($cacheDir);
        if ('' === trim($cacheDirRealPath, '.\\/ ') || 0 !== preg_match('~^[A-Za-z]+:(\\\\|/)?$~', $cacheDirRealPath)) {
            throw new \InvalidArgumentException(sprintf('Invalid cache directory "%s"', $cacheDirRealPath));
        }

        $this->cacheDir = $cacheDir;

        return $this;
    }

    /**
     * Set storage mode
     *
     * @param int $storageMode see FilesystemCache::STORAGE_* constants
     * @return FilesystemDriver
     */
    public function setStorageMode($storageMode)
    {
        if (self::STORAGE_NORMAL !== $storageMode && self::STORAGE_PHP !== $storageMode) {
            throw new \InvalidArgumentException('Invalid storage mode');
        }

        $this->storageMode = $storageMode;

        return $this;
    }

    /**
     * Set temporary directory
     *
     * If it does not exist, it is created (unless NULL was passed).
     *
     * If temporary directory is NULL, the system's default will be used.
     *
     * @param string|null $temporaryDir path to the temporary directory, without trailing slash
     * @throws \RuntimeException if the directory could not be created
     * @return FilesystemDriver
     */
    public function setTemporaryDir($temporaryDir)
    {
        if (null !== $temporaryDir && !is_dir($temporaryDir) && !@mkdir($temporaryDir, 0777, true)) {
            throw new \RuntimeException(sprintf('Could not create temporary directory "%s"', $temporaryDir));
        }

        $this->temporaryDir = $temporaryDir;

        return $this;
    }

    /**
     * Set whether temporary files should be used
     *
     * @param bool $useTemporaryFiles
     * @return FilesystemDriver
     */
    public function setUseTemporaryFiles($useTemporaryFiles)
    {
        $this->useTemporaryFiles = $useTemporaryFiles;

        return $this;
    }

    /**
     * Set whether unlink should be used
     *
     * @param bool $useUnlink
     * @return FilesystemDriver
     */
    public function setUseUnlink($useUnlink)
    {
        $this->useUnlink = $useUnlink;

        return $this;
    }

    public function exists($key)
    {
        return $this->getEntry($key)->isValid();
    }

    public function fetch($key)
    {
        return $this->getEntry($key)->read();
    }

    public function store($key, $value, $overwrite, $ttl = 0)
    {
        $success = false;

        if ($overwrite) {
            // write an entry even if it already exists
            if ($this->useTemporaryFiles) {
                
                // using a temporary file
                $temporaryFile = new TemporaryFile(null, $this->temporaryDir);

                $entry = new FilesystemEntry(
                    $temporaryFile->getPathname(),
                    self::STORAGE_PHP === $this->storageMode,
                    false,
                    true,
                    false,
                    false
                );

                if ($entry->write($value, (int) $ttl)) {
                    $entry->close();

                    if ($temporaryFile->move($this->getPath($key))) {
                        $success = true;
                    }
                }

                if (!$success) {
                    $temporaryFile->discard();
                }
            } else {
                // relying on file locks
                $success = $this->getEntry($key, false, true, true)->write($value, (int) $ttl);
            }
        } else {
            // write a new entry
            $entry = $this->getEntry($key, true, true, true);

            if (!$entry->isValid()) {
                $success = $entry->write($value, $ttl);
            }
        }

        return $success;
    }

    public function expunge($key)
    {
        if ($this->useUnlink) {
            return $this->getEntry($key, false)->remove();
        } else {
            return $this->getEntry($key, false, true)->truncate();
        }
    }

    public function purge()
    {
        return $this->purgeDirectory($this->cacheDir);
    }

    public function filter($prefix)
    {
        $path = $this->getPath($prefix, false);

        if ('/' === substr($path, -1)) {
            $directoryPath = rtrim($path, '/');
            $localPrefix = null;
        } else {
            $directoryPath = dirname($path);
            $localPrefix = basename($path);
            $localPrefixLen = strlen($localPrefix);
        }

        $success = true;

        if (is_dir($directoryPath)) {
            foreach (new \FilesystemIterator($directoryPath) as $item) {
                if (
                    null === $localPrefix
                    || 0 === strncmp($item->getFilename(), $localPrefix, $localPrefixLen)
                ) {
                    if ($item->isDir()) {
                        if (!$this->purgeDirectory($item) || !@rmdir($item)) {
                            $success = false;
                        }
                    } elseif (!@unlink($item)) {
                        $success = false;
                    }
                }
            }
            
            if ($success && null === $localPrefix) {
                @rmdir($directoryPath);
            }
        }

        return $success;
    }

    /**
     * @param string $path path to the directory
     * @return bool
     */
    protected function purgeDirectory($path)
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $path,
            \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS
        );

        $iterator = new \RecursiveIteratorIterator(
            $directoryIterator,
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $success = true;

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!@rmdir($item)) {
                    $success = false;
                }
            } elseif (!@unlink($item)) {
                $success = false;
            }
        }

        return $success;
    }

    public function modifyInteger($key, $offset, &$success = null)
    {
        list($success, $newValue) = $this
            ->getEntry($key, true, true)
            ->update(function ($currentValue) use ($offset) {
                if (is_int($currentValue)) {
                    return $currentValue + $offset;
                } else {
                    return false;
                }
            })
        ;

        return $newValue;
    }

    /**
     * @param string $key
     * @param bool   $addFileExtension
     * @return string
     */
    protected function getPath($key, $addFileExtension = true)
    {
        return
            "{$this->cacheDir}/"
            . str_replace('.', '/', $key)
            . ($addFileExtension
                ? (self::STORAGE_PHP === $this->storageMode
                    ? '.php'
                    : '.dat')
                : ''
            )
        ;
    }

    /**
     * @param string $key
     * @param bool   $read
     * @param bool   $write
     * @param bool   $create
     * @return FilesystemEntry
     */
    protected function getEntry($key, $read = true, $write = false, $create = false)
    {
        return new FilesystemEntry(
            $this->getPath($key),
            self::STORAGE_PHP === $this->storageMode,
            $read,
            $write,
            $create
        );
    }
}
