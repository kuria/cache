<?php

namespace Kuria\Cache\Driver;

/**
 * Filesystem driver entry
 *
 * Structure of the entry files (in order):
 *
 *      Header:
 *          <php-header>    (24 char string; only present if the entry is a PHP file)
 *          <data-length>   (integer - length of the data)
 *          <created-at>    (integer - unix timestamp)
 *          <ttl>           (integer - number of seconds to live)
 *
 *      Body:
 *          <data-block>    (string of length specified by <data-length>)
 *
 * @author ShiraNai7 <shira.cz>
 */
class FilesystemEntry
{
    /** @var array <read 1/0, write 1/0, create 1/0, mode or false> */
    protected static $modeMap = array(
        true => array(
            true => array(
                true => 'c+',
                false => 'r+',
            ),
            false => array(
                false => 'r',
            ),
        ),
        false => array(
            true => array(
                true => 'w',
                false => 'r+', // there is no non-creating write-only mode :/
            ),
            false => array(
                false => false, // handle-less mode
            ),
        ),
    );
    /** @var int */
    protected static $headerLength;
    /** @var string */
    protected static $headerReadFormat;
    /** @var string */
    protected static $headerWriteFormat;

    /** @var string */
    protected $path;
    /** @var bool */
    protected $isPhpFile;
    /** @var bool */
    protected $read;
    /** @var bool */
    protected $write;
    /** @var bool */
    protected $create;
    /** @var bool */
    protected $lock;
    /** @var resource|null */
    protected $handle;

    /**
     * @param string $path
     * @param bool   $isPhpFile
     * @param bool   $read
     * @param bool   $write
     * @param bool   $create
     * @param bool   $lock
     * @throws \InvalidArgumentException if the combination of the access flags is not supported
     */
    public function __construct($path, $isPhpFile, $read, $write, $create, $lock = true)
    {
        if (!isset(static::$modeMap[$read][$write][$create])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported access flag combination: read=%d, write=%d, create=%d',
                $read,
                $write,
                $create
            ));
        }
        if (static::$headerLength === null) {
            static::detectPackSettings();
        }

        $this->path = $path;
        $this->isPhpFile = $isPhpFile;
        $this->read = $read;
        $this->write = $write;
        $this->create = $create;
        $this->lock = $lock;
    }

    /**
     * Automatically close upon destruction
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Free the resources and locks associated with this entry
     */
    public function close()
    {
        if ($this->handle !== null) {
            if ($this->handle !== false) {
                if ($this->lock) {
                    flock($this->handle, LOCK_UN);
                }
                @fclose($this->handle);
            }

            $this->handle = null;
        }
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return
            $this->initHandle(true, false)
            && ($header = $this->readHeader()) !== (false)
            && $this->isFresh($header);
    }

    /**
     * @return mixed false on failure
     */
    public function read()
    {
        if (
            $this->initHandle(true, false)
            && ($header = $this->readHeader()) !== (false)
            && $this->isFresh($header)
        ) {
            $dataBlock = fread($this->handle, $header['data_length']);

            return @unserialize($dataBlock);
        }

        return false;
    }

    /**
     * @param mixed $data
     * @param int   $ttl
     * @return bool
     */
    public function write($data, $ttl = 0)
    {
        if ($this->initHandle(false, true) !== false) {
            $headerLength = ($this->isPhpFile ? 24 : 0) + static::$headerLength;

            $dataBlock = serialize($data);
            $dataLength = strlen($dataBlock);

            $totalLength = $headerLength + $dataLength;

            if (@ftruncate($this->handle, $totalLength)) {
                $writtenBytes = 0;

                // php header
                if ($this->isPhpFile) {
                    $writtenBytes += fwrite($this->handle, '<?php __halt_compiler();');
                }

                // header
                $writtenBytes += fwrite(
                    $this->handle,
                    pack(static::$headerWriteFormat, $dataLength, time(), $ttl)
                );

                // data block
                $writtenBytes += fwrite($this->handle, $dataBlock);
                
                return $totalLength === $writtenBytes;
            }
        }

        return false;
    }

    /**
     * Update an existing value using a callback
     *
     *  - if the callback returns FALSE, no update will be performed.
     *  - updating does not change existing TTL of the entry
     *
     * @param callable $callback callback($currentValue): mixed
     * @return array success 1/0, new value or false
     */
    public function update($callback)
    {
        if (
            $this->initHandle(true, true)
            && ($header = $this->readHeader()) !== (false)
            && $this->isFresh($header)
        ) {
            $dataBlock = fread($this->handle, $header['data_length']);
            $data = @unserialize($dataBlock);

            if (
                $data !== false
                && ($newValue = call_user_func($callback, $data)) !== (false)
                && $this->write($newValue, $header['ttl'])
            ) {
                return array(true, $newValue);
            }
        }

        return array(false, false);
    }

    /**
     * Remove the file
     *
     * @return bool
     */
    public function remove()
    {
        $this->close();

        return @unlink($this->path);
    }

    /**
     * Truncate the entry to zero length
     *
     * @return bool
     */
    public function truncate()
    {
        $this->initHandle(false, true);

        if ($this->handle !== false) {
            return @ftruncate($this->handle, 0);
        }

        return false;
    }

    /**
     * @param array $header
     * @return bool
     */
    protected function isFresh(array $header)
    {
        return $header['ttl'] === 0 || $header['created_at'] + $header['ttl'] > time();
    }
    
    /**
     * @return array|bool false on failure
     */
    protected function readHeader()
    {
        clearstatcache(true, $this->path);
        $fileSize = filesize($this->path);

        $totalHeaderLength = ($this->isPhpFile ? 24 : 0) + static::$headerLength;

        if ($fileSize >= $totalHeaderLength) {
            if ($this->isPhpFile) {
                fseek($this->handle, 24);
            }

            $header = unpack(static::$headerReadFormat, fread($this->handle, static::$headerLength));

            if ($header !== false && $fileSize === $totalHeaderLength + $header['data_length']) {
                return $header;
            }
        }

        return false;
    }

    /**
     * @param bool $requiresRead
     * @param bool $requiresWrite
     * @throws \LogicException if the requirements are not met
     * @return bool
     */
    protected function initHandle($requiresRead, $requiresWrite)
    {
        // verify the requirements
        if ($requiresRead && !$this->read) {
            throw new \LogicException('The operation requires the entry to be opened with read access');
        }
        if ($requiresWrite && !$this->write) {
            throw new \LogicException('The operation requires the entry to be opened with write access');
        }

        // initialize the handle
        if ($this->handle === null) {
            // open
            $mode = static::$modeMap[$this->read][$this->write][$this->create];

            if ($mode === false) {
                throw new \LogicException('The entry has been opened in a handle-less mode, cannot initialize the handle');
            }

            if ($this->create && !is_dir($directoryPath = dirname($this->path))) {
                @mkdir($directoryPath, 0777, true);
            }

            $this->handle = @fopen($this->path, $mode);

            // attempt to acquire an advisory file lock if locking is enabled
            if (
                $this->handle !== false
                && $this->lock
                && !flock($this->handle, $this->write ? LOCK_EX : LOCK_SH)
            ) {
                // could not acquire the lock
                @fclose($this->handle);
                $this->handle = false;
            }
        } elseif ($this->handle !== false) {
            // rewind if already opened
            rewind($this->handle);
        }

        return $this->handle !== false;
    }

    /**
     * Determine pack() format and integer size for the current platform
     */
    protected static function detectPackSettings()
    {
        if (PHP_VERSION_ID >= 50603 && 8 === PHP_INT_SIZE) {
            // 64bit
            static::$headerLength = 3 * 8;
            static::$headerWriteFormat = 'JJJ';
            static::$headerReadFormat = 'Jdata_length/Jcreated_at/Jttl';
        } else {
            // 32bit
            static::$headerLength = 3 * 4;
            static::$headerWriteFormat = 'NNN';
            static::$headerReadFormat = 'Ndata_length/Ncreated_at/Nttl';
        }
    }
}
