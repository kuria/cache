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
        if (!isset(self::$modeMap[$read][$write][$create])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported access flag combination: read=%d, write=%d, create=%d',
                $read,
                $write,
                $create
            ));
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
        if (null !== $this->handle) {
            if (false !== $this->handle) {
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
            && false !== ($header = $this->readHeader())
            && $this->isFresh($header)
        ;
    }

    /**
     * @return mixed false on failure
     */
    public function read()
    {
        if (
            $this->initHandle(true, false)
            && false !== ($header = $this->readHeader())
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
        if (false !== $this->initHandle(false, true)) {
            $headerFormat = $this->isPhpFile ? 'a24iii' : 'iii';
            $headerLength = ($this->isPhpFile ? 24 : 0) + 3 * PHP_INT_SIZE;

            $dataBlock = serialize($data);
            $dataLength = strlen($dataBlock);

            $totalLength = $headerLength + $dataLength;

            if (@ftruncate($this->handle, $totalLength)) {
                $writtenBytes = 0;

                // header
                $writtenBytes += fwrite(
                    $this->handle,
                    $this->isPhpFile
                        ? pack($headerFormat, '<?php __halt_compiler();', $dataLength, time(), $ttl)
                        : pack($headerFormat, $dataLength, time(), $ttl)
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
            && false !== ($header = $this->readHeader())
            && $this->isFresh($header)
        ) {
            $dataBlock = fread($this->handle, $header['data_length']);
            $data = @unserialize($dataBlock);

            if (
                false !== $data
                && false !== ($newValue = call_user_func($callback, $data))
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

        if (false !== $this->handle) {
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
        return 0 === $header['ttl'] || $header['created_at'] + $header['ttl'] > time();
    }
    
    /**
     * @return array|bool false on failure
     */
    protected function readHeader()
    {
        clearstatcache(true, $this->path);
        $fileSize = filesize($this->path);

        $headerFormat = $this->isPhpFile
            ? 'a24php_header/idata_length/icreated_at/ittl'
            : 'idata_length/icreated_at/ittl'
        ;
        $headerLength = ($this->isPhpFile ? 24 : 0) + PHP_INT_SIZE * 3;

        if ($fileSize >= $headerLength) {
            $header = unpack($headerFormat, fread($this->handle, $headerLength));

            if (false !== $header && $fileSize === $headerLength + $header['data_length']) {
                $header['header_length'] = $headerLength;

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
        if (null === $this->handle) {
            // open
            $mode = self::$modeMap[$this->read][$this->write][$this->create];

            if (false === $mode) {
                throw new \LogicException('The entry has been opened in a handle-less mode, cannot initialize the handle');
            }

            if ($this->create && !is_dir($directoryPath = dirname($this->path))) {
                @mkdir($directoryPath, 0777, true);
            }

            $this->handle = @fopen($this->path, $mode);

            // attempt to acquire an advisory file lock if locking is enabled
            if (
                false !== $this->handle
                && $this->lock
                && !flock($this->handle, $this->write ? LOCK_EX : LOCK_SH)
            ) {
                // could not acquire the lock
                @fclose($this->handle);
                $this->handle = false;
            }
        } elseif (false !== $this->handle) {
            // rewind if already opened
            rewind($this->handle);
        }

        return false !== $this->handle;
    }
}
