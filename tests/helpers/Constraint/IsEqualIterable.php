<?php declare(strict_types=1);

namespace Kuria\Cache\Test\Constraint;

use Kuria\Cache\Helper\IterableHelper;
use PHPUnit\Framework\Constraint\IsEqual;

class IsEqualIterable extends IsEqual
{
    function __construct($value)
    {
        parent::__construct(IterableHelper::toArray($value), 0.0, 10, true);
    }

    function evaluate($other, $description = '', $returnResult = false)
    {
        return parent::evaluate(IterableHelper::toArray($other), $description, $returnResult);
    }
}
