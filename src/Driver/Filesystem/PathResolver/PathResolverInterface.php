<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\PathResolver;

use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;

interface PathResolverInterface
{
    /**
     * Get path for the given format and key
     *
     * The returned path should begin with a forward slash ("/")
     */
    function resolve(FileFormatInterface $format, string $key): string;
}
