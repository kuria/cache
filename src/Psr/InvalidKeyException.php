<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use Psr\Cache\InvalidArgumentException;

class InvalidKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
