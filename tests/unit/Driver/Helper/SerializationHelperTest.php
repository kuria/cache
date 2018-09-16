<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Helper;

use Kuria\Cache\Driver\Helper\Exception\DeserializationFailedException;
use Kuria\Cache\Test\Undeserializable;
use Kuria\DevMeta\Test;

/**
 * @group unit
 */
class SerializationHelperTest extends Test
{
    function testShouldUnserialize()
    {
        $this->assertSame([1, 2, 3], SerializationHelper::smartUnserialize(serialize([1, 2, 3])));
        $this->assertFalse(SerializationHelper::smartUnserialize(serialize(false)));
    }

    function testShouldThrowExceptionOnMalformedData()
    {
        $this->expectException(DeserializationFailedException::class);
        $this->expectExceptionMessage('Unserialization failed - data possibly malformed');

        SerializationHelper::smartUnserialize('not_serialized_data');
    }

    function testShouldWrapUnserializeExceptions()
    {
        $this->expectException(DeserializationFailedException::class);
        $this->expectExceptionMessage('An exception was thrown during unserialization');

        SerializationHelper::smartUnserialize(serialize(new Undeserializable()));
    }
}
