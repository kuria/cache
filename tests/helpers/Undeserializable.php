<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

class Undeserializable
{
    function __wakeup()
    {
        throw new \Exception('Wakeup exception');
    }
}
