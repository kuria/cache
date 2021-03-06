<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\File\BinaryFileFormat;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\PathResolver\HashedPathResolver;
use Kuria\Cache\Driver\Filesystem\PathResolver\PathResolverInterface;

class EntryFactory implements EntryFactoryInterface
{
    /** @var FileFormatInterface */
    private $fileFormat;

    /** @var PathResolverInterface */
    private $pathResolver;

    /** @var string */
    private $temporaryDirPath;

    /** @var int */
    private $umask;

    function __construct(
        ?FileFormatInterface $fileFormat = null,
        ?PathResolverInterface $pathResolver = null,
        ?string $temporaryDirPath = null,
        ?int $umask = null
    ) {
        $this->fileFormat = $fileFormat ?? new BinaryFileFormat();
        $this->pathResolver = $pathResolver ?? new HashedPathResolver();
        $this->temporaryDirPath = $temporaryDirPath ?? sys_get_temp_dir();
        $this->umask = $umask ?? 002;
    }

    function fromPath(string $path): EntryInterface
    {
        return new Entry($this->fileFormat, $path, $this->temporaryDirPath, $this->umask);
    }

    function fromKey(string $cachePath, string $key): EntryInterface
    {
        return $this->fromPath($cachePath . $this->pathResolver->resolve($this->fileFormat, $key));
    }
}
