<?php declare(strict_types=1);

namespace Kuria\Cache;

use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CacheEventTest extends TestCase
{
    function testCreatingCacheEvent()
    {
        $event = new CacheEvent('key', 'value');

        $this->assertSame('key', $event->key);
        $this->assertSame('value', $event->value);
    }
}
