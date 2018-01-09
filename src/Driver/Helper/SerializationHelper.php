<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Helper;

use Kuria\Cache\Driver\Helper\Exception\DeserializationFailedException;

abstract class SerializationHelper
{
    /**
     * Attempt to unserialize the given data and properly handle any error states
     *
     * @throws DeserializationFailedException on failure
     * @return mixed
     */
    static function smartUnserialize(string $data)
    {
        try {
            $value = @unserialize($data);
        } catch (\Throwable $e) {
            throw new DeserializationFailedException('An exception was thrown during unserialization', 0, $e);
        }

        // distinguish between "false on error" and a serialized FALSE value
        if ($value === false && $data !== static::getSerializedFalse()) {
            throw new DeserializationFailedException('Unserialization failed - data possibly malformed');
        }

        return $value;
    }

    protected static function getSerializedFalse(): string
    {
        static $serializedFalse;

        if ($serializedFalse === null) {
            $serializedFalse = serialize(false);
        }

        return $serializedFalse;
    }
}
