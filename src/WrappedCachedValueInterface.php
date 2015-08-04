<?php

namespace Kuria\Cache;

/**
 * Wrapped cache value interface
 *
 * Used to mark wrapped cache values. If the value is not unwrapped by
 * cache extensions, it will be discarded.
 *
 * This makes it possible to remove cache extensions without having to always
 * clear the entire cache.
 *
 * @author ShiraNai7 <shira.cz>
 */
interface WrappedCachedValueInterface
{
}
