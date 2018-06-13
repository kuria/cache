<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Helper;

use Kuria\Cache\Test\TimeMachine;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class TtlHelperTest extends TestCase
{
    /**
     * @dataProvider provideTtlForExpirationCheck
     */
    function testShouldDetermineIfTtlShouldExpire(?int $ttl, bool $expectedResult)
    {
        $this->assertSame($expectedResult, TtlHelper::shouldExpire($ttl));
    }

    function provideTtlForExpirationCheck(): array
    {
        return [
            // ttl, expectedResult
            [123, true],
            [60, true],
            [1, true],
            [0, false],
            [-1, false],
            [-10, false],
            [null, false],
        ];
    }

    /**
     * @dataProvider provideTtlForNormalization
     */
    function testShouldNormalize(?int $ttl, int $expectedResult)
    {
        $this->assertSame($expectedResult, TtlHelper::normalize($ttl));
    }

    function provideTtlForNormalization(): array
    {
        return [
            // ttl, expectedResult
            [123, 123],
            [60, 60],
            [1, 1],
            [0, 0],
            [-1, 0],
            [-10, 0],
            [null, 0],
        ];
    }

    /**
     * @dataProvider provideTtlForExpirationTimeConversion
     */
    function testShouldConvertToExpirationTime(?int $ttl, int $now, int $expectedResult)
    {
        TimeMachine::setTime([__NAMESPACE__], $now, function () use ($ttl, $expectedResult) {
            $this->assertSame($expectedResult, TtlHelper::toExpirationTime($ttl));
        });
    }

    function provideTtlForExpirationTimeConversion(): array
    {
        return [
            // ttl, now, expectedResult
            [123, 10, 133],
            [60, 100, 160],
            [1, 5, 6],
            [0, 123, 0],
            [-1, 456, 0],
            [-10, 789, 0],
            [null, 888, 0],
        ];
    }
}
