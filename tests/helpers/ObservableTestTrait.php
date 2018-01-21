<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Event\ObservableInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

trait ObservableTestTrait
{
    protected function expectEvent(ObservableInterface $observable, string $event, ...$expectedArguments): void
    {
        $listener = $this->createMock(EventListener::class);

        $listener->expects(TestCase::once())
            ->method('__invoke')
            ->with(...$expectedArguments);

        $observable->on($event, $listener);
    }

    protected function expectConsecutiveEvents(ObservableInterface $observable, string $event, array ...$expectedArgumentGroups): void
    {
        $listener = $this->createMock(EventListener::class);
        $callCounter = 0;

        $listener->expects(TestCase::exactly(sizeof($expectedArgumentGroups)))
            ->method('__invoke')
            // cannot use withConsecutive() because it doesn't work with arguments that are modified after the call
            ->willReturnCallback(function (...$args) use (&$callCounter, $expectedArgumentGroups) {
                TestCase::assertEquals($expectedArgumentGroups[$callCounter++], $args);
            });

        $observable->on($event, $listener);
    }

    protected function expectNoEvent(ObservableInterface $observable, string $event): void
    {
        $listener = $this->createMock(EventListener::class);

        $listener->expects(TestCase::never())
            ->method('__invoke');

        $observable->on($event, $listener);
    }

    /**
     * @return MockObject
     */
    abstract protected function createMock($originalClassName);
}

/**
 * @internal
 */
interface EventListener
{
    function __invoke();
}
