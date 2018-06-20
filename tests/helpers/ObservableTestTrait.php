<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Event\ObservableInterface;
use PHPUnit\Framework\TestCase;

trait ObservableTestTrait
{
    protected function expectEvent(ObservableInterface $observable, string $event, ...$expectedArguments): void
    {
        /** @var TestCase $this */
        $listenerMock = $this->createMock(ListenerInterface::class);

        $listenerMock->expects(TestCase::once())
            ->method('__invoke')
            ->with(...$expectedArguments);

        $observable->on($event, $listenerMock);
    }

    protected function expectConsecutiveEvents(ObservableInterface $observable, string $event, array ...$expectedArgumentGroups): void
    {
        /** @var TestCase $this */
        $listenerMock = $this->createMock(ListenerInterface::class);
        $callCounter = 0;

        $listenerMock->expects(TestCase::exactly(count($expectedArgumentGroups)))
            ->method('__invoke')
            // cannot use withConsecutive() because it doesn't work with arguments that are modified after the call
            ->willReturnCallback(function (...$args) use (&$callCounter, $expectedArgumentGroups) {
                TestCase::assertEquals($expectedArgumentGroups[$callCounter++], $args);
            });

        $observable->on($event, $listenerMock);
    }

    protected function expectNoEvent(ObservableInterface $observable, string $event): void
    {
        /** @var TestCase $this */
        $listenerMock = $this->createMock(ListenerInterface::class);

        $listenerMock->expects(TestCase::never())
            ->method('__invoke');

        $observable->on($event, $listenerMock);
    }

    protected function expectNoEvents(ObservableInterface $observable): void
    {
        $this->expectNoEvent($observable, ObservableInterface::ANY_EVENT);
    }
}

/**
 * @internal
 */
interface ListenerInterface
{
    function __invoke();
}
