<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\PathResolver;

use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;

class HashedPathResolver implements PathResolverInterface
{
    /** @var string */
    private $hashAlgo = 'fnv1a64';
    /** @var int */
    private $pathSegmentCount = 1;
    /** @var int */
    private $pathSegmentLength = 2;

    function setHashAlgo(string $hashAlgo): void
    {
        $this->hashAlgo = $hashAlgo;
    }

    function setPathSegmentCount(int $pathSegmentCount): void
    {
        $this->pathSegmentCount = $pathSegmentCount;
    }

    function setPathSegmentLength(int $pathSegmentLength): void
    {
        $this->pathSegmentLength = $pathSegmentLength;
    }

    function resolve(FileFormatInterface $format, string $key): string
    {
        $hash = hash($this->hashAlgo, $key);

        return $this->resolvePath($hash) . '/' . $hash . $format->getFilenameSuffix();
    }

    private function resolvePath(string $hash): string
    {
        $path = '';

        $end = $this->pathSegmentLength * $this->pathSegmentCount;

        if ($end > 0 && !isset($hash[$end - 1])) {
            throw new \LengthException(sprintf(
                'Cannot produce path (segment count = %d and segment length = %d) from hash "%s" (algo = %s)'
                    . ' because the hash is not long enough (need at least %d bytes, got %d)',
                $this->pathSegmentCount,
                $this->pathSegmentLength,
                $hash,
                $this->hashAlgo,
                $end,
                strlen($hash)
            ));
        }

        for ($i = 0; $i < $end; ++$i) {
            if ($i % $this->pathSegmentLength === 0) {
                $path .= '/';
            }

            $path .= $hash[$i];
        }

        return $path;
    }
}
