<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\Cache;
use Kuria\Cache\Provider\Helper\FileLock;
use Kuria\Cache\Provider\Helper\TemporaryFile;

/**
 * Filesystem cache implementation
 *
 * @author ShiraNai7 <shira.cz>
 */
class FilesystemCache extends Cache
{
    /** Storage mode - .dat files that MUST NOT be publicly accessible */
    const STORAGE_NORMAL = 0;
    /** Storage mode - .php files that are safe to be in a public directory */
    const STORAGE_PHP = 1;

    /** Write mode - write and replace if entry exists */
    const WRITE_SET = 0;
    /** Write mode - write only if entry does not exist */
    const WRITE_ADD = 1;
    /** Write mode - rewrite data of existing entry only */
    const WRITE_UPDATE = 2;
    /** Write mode - rewrite integer data of existing entry only */
    const WRITE_OFFSET = 3;
    /** Write mode - truncate */
    const WRITE_TRUNCATE = 4;

    /** Read mode - read metadata only - returns array */
    const READ_META = 0;
    /** Read mode - read data only - returns mixed */
    const READ_DATA = 1;
    /** Read mode - read both metadata and data - returns array(meta, data) */
    const READ_BOTH = 3;
    /** Read mode - go to data offset - returns data length */
    const READ_GOTO_DATA = 4;

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

        if ('\\' !== DIRECTORY_SEPARATOR) {
            // use "better" defaults if not on windows
            $this->useTemporaryFiles = true;
            $this->useUnlink = true;
        }
    }

    /**
     * Get cache directory
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Set cache directory
     *
     * If it does not exist, it is created.
     *
     * @param string $cacheDir path to the cache directory, without trailing slash
     * @throws \RuntimeException         if the directory could not be created
     * @throws \InvalidArgumentException if the directory is a filesystem root
     * @return FilesystemCache
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
     * Get storage mode
     *
     * @return int
     */
    public function getStorageMode()
    {
        return $this->storageMode;
    }

    /**
     * Set storage mode
     *
     * @param int $storageMode see FilesystemCache::STORAGE_* constants
     * @return FilesystemCache
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
     * Get temporary directory
     *
     * @return string|null
     */
    public function getTemporaryDir()
    {
        return $this->temporaryDir;
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
     * @return FilesystemCache
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
     * See if temporary files are used
     *
     * @return bool
     */
    public function getUseTemporaryFiles()
    {
        return $this->useTemporaryFiles;
    }

    /**
     * Set whether temporary files should be used
     *
     * @param bool $useTemporaryFiles
     * @return FilesystemCache
     */
    public function setUseTemporaryFiles($useTemporaryFiles)
    {
        $this->useTemporaryFiles = $useTemporaryFiles;

        return $this;
    }

    /**
     * See if unlink is used
     *
     * @return bool
     */
    public function getUseUnlink()
    {
        return $this->useUnlink;
    }

    /**
     * Set whether unlink should be used
     *
     * @param bool $useUnlink
     * @return FilesystemCache
     */
    public function setUseUnlink($useUnlink)
    {
        $this->useUnlink = $useUnlink;

        return $this;
    }

    protected function exists($key)
    {
        return false !== $this->read($key, self::READ_META);
    }

    protected function fetch($key)
    {
        return $this->read($key, self::READ_DATA);
    }

    protected function store($key, $data, $overwrite, $ttl)
    {
        return $this->write(
            $key,
            $overwrite ? self::WRITE_SET : self::WRITE_ADD,
            $data,
            array(
                'ttl' => $ttl,
                'created_at' => time(),
            )
        );
    }

    protected function expunge($key)
    {
        if ($this->useUnlink) {
            return @unlink($this->getPath($key));
        } else {
            return $this->write($key, self::WRITE_TRUNCATE);
        }
    }

    protected function purge($prefix)
    {
        $directoryPath = $this->cacheDir;
        if ('' !== $prefix) {
            $directoryPath .= '/' . rtrim($prefix, '/');
        }

        if (is_dir($directoryPath)) {
            $directoryIterator = new \RecursiveDirectoryIterator(
                $directoryPath,
                \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS
            );

            $iterator = new \RecursiveIteratorIterator(
                $directoryIterator,
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            $success = true;
            foreach ($iterator as $item) {
                if (is_dir($item)) {
                    if (!@rmdir($item)) {
                        $success = false;
                    }
                } elseif (!@unlink($item)) {
                    $success = false;
                }
            }

            return $success;
        }

        return true;
    }

    protected function modifyInteger($key, $offset, &$success = null)
    {
        $newValue = false;
        $success = $this->write($key, self::WRITE_OFFSET, $offset, null, $newValue);

        return $newValue;
    }

    /**
     * Write an entry
     *
     * @param string     $key
     * @param int        $mode     see FilesystemCache::WRITE_* constants
     * @param mixed      $data     data to store
     * @param array|null $meta     required for WRITE and ADD mode
     * @param mixed      $newValue used in OFFSET mode
     * @return bool
     */
    protected function write($key, $mode, $data = null, array $meta = null, &$newValue = null)
    {
        // get path to the file
        $path = $this->getPath($key);

        // determine mode
        $canUseTmpFile = false;
        switch ($mode) {
            case self::WRITE_SET:
            case self::WRITE_ADD:
                // write, creating
                $handleMode = 'c';
                if (self::WRITE_SET === $mode) {
                    $canUseTmpFile = true;
                }
                break;
            case self::WRITE_UPDATE:
            case self::WRITE_OFFSET:
            case self::WRITE_TRUNCATE:
                // read/write, no creating
                $handleMode = 'r+';
                break;
            default:
                throw new \InvalidArgumentException('Invalid mode');
        }

        // create the directory if it does not exist
        $pathDir = dirname($path);
        if (!is_dir($pathDir)) {
            @mkdir($pathDir, 0777, true);
        }

        // open handle
        if ($canUseTmpFile && $this->useTemporaryFiles) {
            $usingTmpFile = true;
            $tmpFile = new TemporaryFile(null, $this->temporaryDir);
            $handle = @fopen($tmpFile, $handleMode);
        } else {
            $usingTmpFile = false;
            $handle = @fopen($path, $handleMode);
        }
        if (false === $handle) {
            if ($usingTmpFile) {
                $tmpFile->discard();
            }
            return false;
        }

        // acquire exclusive lock
        if (!$usingTmpFile) {
            $lock = FileLock::acquireExclusive($handle);
            if (false === $lock) {
                @fclose($handle);
                return false;
            }
        }

        // clear stat cache for this file to always read the real size
        if (!$usingTmpFile) {
            clearstatcache(true, $path);
        }

        // write
        $success = $this->writeHandle($handle, $usingTmpFile ? 0 : filesize($path), $mode, $data, $meta, $newValue);

        // release the lock
        if (!$usingTmpFile) {
            $lock->release();
        }

        // close handle
        @fclose($handle);

        // move the temporary file (if used)
        if ($usingTmpFile) {
            $success = @rename($tmpFile, $path);
            if ($success) {
                $tmpFile->keep();
            } else {
                $tmpFile->discard();
            }
        }

        return $success;
    }

    /**
     * Write data to the the given handle
     *
     * @param resource   $handle    opened file handle with exclusive lock
     * @param int        $length    length of the existing data
     * @param int        $mode      mode used to open the file (see FilesystemCache::WRITE_* constants)
     * @param mixed      $data      data to write
     * @param array|null $meta      required for WRITE and ADD mode
     * @param mixed      &$newValue used in OFFSET mode
     * @return bool
     */
    protected function writeHandle($handle, $length, $mode, $data, array $meta = null, &$newValue = null)
    {
        // handle existing data
        if (0 === $length) {
            // the file is empty
            if (self::WRITE_UPDATE === $mode || self::WRITE_OFFSET === $mode) {
                // cannot update/offset empty file
                return false;
            }
        } else {
            // the file contains data
            if (self::WRITE_ADD === $mode) {
                // in ADD mode, the file must be empty
                return false;
            }
            if (self::WRITE_SET === $mode) {
                // in write mode, the file must be truncated
                if (!@ftruncate($handle, 0)) {
                    return false;
                }
            }
        }

        // truncating?
        if (self::WRITE_TRUNCATE === $mode) {
            if ($length > 0) {
                return @ftruncate($handle, 0);
            }

            return false;
        }

        // write "<?php __halt_compiler();"?
        if (self::STORAGE_PHP === $this->storageMode) {
            fwrite($handle, '<?php __halt_compiler();');
        }

        // write metadata?
        if (self::WRITE_UPDATE === $mode || self::WRITE_OFFSET === $mode) {
            // we keep the current metadata in update/offset mode
            // lets just skip to the data
            $dataLength = $this->readHandle($handle, $length, self::READ_GOTO_DATA);
        } else {
            // write
            if (null === $meta) {
                throw new \InvalidArgumentException('Meta data must be given if mode is WRITE/ADD');
            }

            // serialize  first
            $metaSerialized = serialize($meta);

            // write length
            fwrite($handle, pack('N', strlen($metaSerialized)));

            // write serialized string
            fwrite($handle, $metaSerialized);
        }

        // read current data in offset mode
        if (self::WRITE_OFFSET === $mode) {
            $dataOffset = ftell($handle) - 4; // current offset - data length

            // read data, unserialize and verify
            $currentData = fread($handle, $dataLength);
            $currentData = @unserialize($currentData);
            if (!is_int($currentData)) {
                // unserializing failed or not an integer value
                return false;
            }

            // return to the beginning of data
            fseek($handle, $dataOffset);

            // compute new value
            $data = $currentData + $data;
            $newValue = $data;
        }

        // serialize data
        $data = serialize($data);
        $dataLength = strlen($data);

        // truncate existing data
        if (0 !== $length) {
            // ftell() = bytes required until now
            // + 4 = bytes required for data length
            // + $dataLength = bytes required for the data
            ftruncate($handle, ftell($handle) + 4 + $dataLength);
        }

        // write length
        fwrite($handle, pack('N', $dataLength));

        // write data
        fwrite($handle, $data);

        return fflush($handle);
    }

    /**
     * Read an entry
     *
     * @param string $key
     * @param int    $mode see FilesystemCache::READ_* constants
     * @return mixed depends on mode, false on failure
     */
    protected function read($key, $mode)
    {
        // get path to the file
        $path = $this->getPath($key);

        // open handle
        $handle = @fopen($path, 'r');
        if (false === $handle) {
            return false;
        }

        // acquire shared lock
        $lock = FileLock::acquireShared($handle);
        if (false === $lock) {
            @fclose($handle);
            return false;
        }

        // clear stat cache for this file to always read the real size
        clearstatcache(true, $path);

        // read
        $result = $this->readHandle($handle, filesize($path), $mode);

        // release the lock
        $lock->release();

        // close handle
        @fclose($handle);

        return $result;
    }

    /**
     * Read data from the given handle
     *
     * @param resource $handle         opened file handle with at least shared lock
     * @param int      $length         length of the existing data
     * @param int      $mode           see FilesystemCache::READ_* constants
     * @param bool     $checkFreshness verify TTL 1/0
     * @return mixed depends on mode, false on failure
     */
    protected function readHandle($handle, $length, $mode, $checkFreshness = true)
    {
        $meta = null;
        $data = null;

        if (self::STORAGE_PHP === $this->storageMode) {
            $baseLength = 24 + 8; // "<?php __halt_compiler();" length + meta length + data length
        } else {
            $baseLength = 8; // meta length + data length
        }

        // check length
        if ($length < $baseLength) {
            // empty or corrupted file
            return false;
        }

        // skip "<?php __halt_compiler();"?
        if (self::STORAGE_PHP === $this->storageMode) {
            fseek($handle, 24);
        }

        // read length of metadata
        $metaLength = unpack('N', fread($handle, 4));
        if (false !== $metaLength) {
            $metaLength = $metaLength[1];
            if ($metaLength > $length - $baseLength) {
                // invalid meta length
                return false;
            }
        } else {
            return false;
        }

        // read metadata
        if ($checkFreshness || self::READ_BOTH === $mode || self::READ_META === $mode) {
            $meta = fread($handle, $metaLength);
        }

        // read data
        if (self::READ_META !== $mode) {
            // skip unread metadata
            if (null === $meta) {
                fseek($handle, $metaLength, SEEK_CUR);
            }

            // read length of data
            $dataLen = unpack('N', fread($handle, 4));
            if (false !== $dataLen) {
                $dataLen = $dataLen[1];
                if ($dataLen > $length - $baseLength - $metaLength) {
                    // invalid data length
                    return false;
                }
            } else {
                return false;
            }

            // read data if mode is BOTH or DATA
            if (self::READ_GOTO_DATA !== $mode) {
                $data = fread($handle, $dataLen);
            }
        }

        // unserialize meta
        if (null !== $meta) {
            $meta = @unserialize($meta);
            if (false === $meta) {
                return false;
            }
        }

        // check freshness
        if ($checkFreshness && !$this->isFresh($meta)) {
            // stale
            return false;
        }

        // unserialize data
        if (null !== $data) {
            $data = @unserialize($data);
            if (false === $data) {
                return false;
            }
        }

        // return value according to mode
        switch ($mode) {
            case self::READ_META:
                return $meta;
            case self::READ_DATA:
                return $data;
            case self::READ_BOTH:
                return array($meta, $data);
            case self::READ_GOTO_DATA:
                return $dataLen;
            default:
                throw new \InvalidArgumentException('Invalid mode');
        }
    }

    /**
     * Check freshness of the given metadata
     *
     * @param mixed $meta
     * @return bool
     */
    protected function isFresh($meta)
    {
        if (
            is_array($meta)
            && (0 === $meta['ttl'] || $meta['created_at'] + $meta['ttl'] > time())
        ) {
            // the entry is valid and fresh
            return true;
        } else {
            // invalid or stale
            return false;
        }
    }

    /**
     * Get entry file path
     *
     * @param string $key
     * @return string
     */
    protected function getPath($key)
    {
        $path = "{$this->cacheDir}/{$key}.";

        if (self::STORAGE_PHP === $this->storageMode) {
            $path .= 'php';
        } else {
            $path .= 'dat';
        }

        return $path;
    }
}
