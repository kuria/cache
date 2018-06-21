<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class PsrCacheHelperTest extends TestCase
{
    /**
     * @dataProvider provideIntervals
     */
    function testShouldConvertDateIntervalToTtl(\DateInterval $interval, ?int $expectedTtl)
    {
        $this->assertEquals($expectedTtl, PsrCacheHelper::convertDateIntervalToTtl($interval));
    }

    function provideIntervals(): array
    {
        $negativeInterval = new \DateInterval('PT60S');
        $negativeInterval->invert = 1;

        return [
            // interval, expectedTtl
            [new \DateInterval('P2Y3M5DT3H5M25S'), 71405125],
            [new \DateInterval('P5Y20M60DT30H70M80S'), 215608280],
            [$negativeInterval, null],
        ];
    }
}