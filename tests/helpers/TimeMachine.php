<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use phpmock\functions\FixedValueFunction;
use phpmock\Mock;
use phpmock\MockBuilder;

/**
 * Temporarily mocks the time() function in a given namespace
 */
abstract class TimeMachine
{
    /**
     * Freeze time for the duration of the callback
     *
     * The frozen timestamp is passed to the callback.
     */
    static function freezeTime(array $namespaces, callable $callback): void
    {
        $time = time();

        static::setTime(
            $namespaces,
            $time,
            function () use ($time, $callback) {
                $callback($time);
            }
        );
    }

    /**
     * Override time for the duration of the callback
     */
    static function setTime(array $namespaces, int $time, callable $callback): void
    {
        /** @var Mock[] $timeMocks */
        $timeMocks = [];

        $functionProvider = new FixedValueFunction($time);

        try {
            foreach ($namespaces as $namespace) {
                $timeMock = (new MockBuilder())
                    ->setNamespace($namespace)
                    ->setName('time')
                    ->setFunctionProvider($functionProvider)
                    ->build();

                $timeMock->enable();
                $timeMocks[] = $timeMock;
            }

            $callback();
        } finally {
            foreach ($timeMocks as $timeMock) {
                $timeMock->disable();
            }
        }
    }
}
