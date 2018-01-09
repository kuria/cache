<?php declare(strict_types=1);

namespace Kuria\Cache\Helper;

use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class IterableHelperTest extends TestCase
{
    function testIterableToArray()
    {
        $this->assertSame([1, 2, 3], IterableHelper::toArray([1, 2, 3]));
        $this->assertSame([1, 2, 3], IterableHelper::toArray(new \ArrayIterator([1, 2, 3])));
        $this->assertSame([1, 2, 3], IterableHelper::toArray((function () { yield 1; yield 2; yield 3; })()));
    }
}
