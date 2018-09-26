<?php declare(strict_types=1);

namespace Kuria\Cache\Test;

use Kuria\Event\ObservableInterface;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount;
use PHPUnit\Framework\MockObject\MockObject;

trait ObservableTestTrait
{
    abstract static function assertLooselyIdentical($expected, $actual, bool $canonicalizeKeys = false, string $message = ''): void;

    abstract static function once(): InvokedCount;

    abstract static function exactly(int $count): InvokedCount;

    abstract static function never(): InvokedCount;

    abstract protected function createMock($originalClassName): MockObject;

    protected function expectEvent(ObservableInterface $observable, string $event, ...$expectedArguments): void
    {
        $listenerMock = $this->createMock(ListenerInterface::class);

        $listenerMock->expects($this->once())
            ->method('__invoke')
            ->with(...$expectedArguments);

        $observable->on($event, $listenerMock);
    }

    protected function expectConsecutiveEvents(ObservableInterface $observable, string $event, array ...$expectedArgumentGroups): void
    {
        $listenerMock = $this->createMock(ListenerInterface::class);
        $callCounter = 0;

        $listenerMock->expects($this->exactly(count($expectedArgumentGroups)))
            ->method('__invoke')
            // cannot use withConsecutive() because it doesn't work with arguments that are modified after the call
            ->willReturnCallback(function (...$args) use (&$callCounter, $expectedArgumentGroups) {
                $this->assertLooselyIdentical($expectedArgumentGroups[$callCounter++], $args);
            });

        $observable->on($event, $listenerMock);
    }

    protected function expectNoEvent(ObservableInterface $observable, string $event): void
    {
        $listenerMock = $this->createMock(ListenerInterface::class);

        $listenerMock->expects($this->never())
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
