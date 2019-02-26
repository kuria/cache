<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

class Unexportable
{
    static function __set_state($a)
    {
        throw new \Exception('Set state exception');
    }
}
