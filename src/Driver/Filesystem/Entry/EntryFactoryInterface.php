<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

interface EntryFactoryInterface
{
    function fromPath(string $path): EntryInterface;
    function fromKey(string $cachePath, string $key): EntryInterface;
}
