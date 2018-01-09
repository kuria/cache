<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Cache\Test\Constraint\IsEqualIterable;
use Kuria\Cache\Test\Constraint\IsIdenticalIterable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;

trait IterableAssertionTrait
{
    protected function isSameIterable(iterable $expected): Constraint
    {
        return new IsIdenticalIterable($expected);
    }

    protected function isEqualIterable(iterable $expected): Constraint
    {
        return new IsEqualIterable($expected);
    }

    protected function assertSameIterable(iterable $expected, iterable $actual): void
    {
        Assert::assertThat($actual, $this->isSameIterable($expected));
    }

    protected function assertEqualIterable(iterable $expected, iterable $actual): void
    {
        Assert::assertThat($actual, $this->isEqualIterable($expected));
    }
}
