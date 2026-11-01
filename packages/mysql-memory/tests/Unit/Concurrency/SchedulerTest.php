<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use Fiber;
use MySqlMemory\Concurrency\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scheduler::class)]
#[Small]
final class SchedulerTest extends TestCase
{
    public function testAdmitLetsTheWorkOfAFiberWait(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(static fn (): bool => $scheduler->suspendable());
        $scheduler->admit($fiber);
        $fiber->start();

        self::assertTrue($fiber->getReturn());
    }

    public function testDismissForgetsAFiber(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(static fn (): bool => $scheduler->suspendable());
        $scheduler->admit($fiber);
        $scheduler->dismiss($fiber);
        $fiber->start();

        self::assertSame([false, []], [$fiber->getReturn(), $scheduler->fibers]);
    }

    public function testSuspendableAnswersFalseOutsideAFiber(): void
    {
        self::assertFalse((new Scheduler())->suspendable());
    }

    public function testPauseSuspendsTheFiberUntilItIsResumed(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(static fn () => $scheduler->pause());
        $scheduler->admit($fiber);
        $fiber->start();
        $suspended = $fiber->isSuspended();
        $fiber->resume();

        self::assertSame([true, true], [$suspended, $fiber->isTerminated()]);
    }
}
