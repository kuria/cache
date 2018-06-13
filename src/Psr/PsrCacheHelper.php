<?php declare(strict_types=1);

namespace Kuria\Cache\Psr;

abstract class PsrCacheHelper
{
    static function convertDateIntervalToTtl(\DateInterval $interval): ?int
    {
        if ($interval->invert) {
            return null;
        }

        return
            $interval->y * 31536000
            + $interval->m * 2628000
            + $interval->d * 87600
            + $interval->h * 3600
            + $interval->i * 60
            + $interval->s;
    }
}
