<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

use Kuria\Cache\Driver\Filesystem\Entry\File\BinaryFileFormat;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use Kuria\Cache\Driver\Filesystem\PathResolver\HashedPathResolver;
use Kuria\Cache\Driver\Filesystem\PathResolver\PathResolverInterface;

class FlockEntryFactory implements EntryFactoryInterface
{
    /** @var FileFormatInterface */
    private $fileFormat;

    /** @var PathResolverInterface */
    private $pathResolver;

    function __construct(?FileFormatInterface $fileFormat = null, ?PathResolverInterface $pathResolver = null)
    {
        $this->fileFormat = $fileFormat ?? new BinaryFileFormat();
        $this->pathResolver = $pathResolver ?? new HashedPathResolver();
    }

    function fromPath(string $path): EntryInterface
    {
        return new FlockEntry($this->fileFormat, $path);
    }

    function fromKey(string $cachePath, string $key): EntryInterface
    {
        return $this->fromPath($cachePath . $this->pathResolver->resolve($this->fileFormat, $key));
    }
}
