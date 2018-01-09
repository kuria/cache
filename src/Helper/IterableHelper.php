<?php declare(strict_types=1);

namespace Kuria\Cache\Helper;

abstract class IterableHelper
{
    static function toArray(iterable $iterable): array
    {
        return is_array($iterable) ? $iterable : iterator_to_array($iterable);
    }
}
