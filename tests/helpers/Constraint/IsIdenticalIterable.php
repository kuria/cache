<?php declare(strict_types=1);

namespace Kuria\Cache\Test\Constraint;

use Kuria\Cache\Helper\IterableHelper;
use PHPUnit\Framework\Constraint\IsIdentical;

class IsIdenticalIterable extends IsIdentical
{
    function __construct($value)
    {
        parent::__construct(IterableHelper::toArray($value));
    }

    function evaluate($other, $description = '', $returnResult = false)
    {
        return parent::evaluate(IterableHelper::toArray($other), $description, $returnResult);
    }
}
