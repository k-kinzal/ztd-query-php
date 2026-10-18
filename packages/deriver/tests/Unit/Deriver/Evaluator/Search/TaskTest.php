<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Search;

use Deriver\Deriver\Evaluator\Search\Task;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TaskTest extends TestCase
{
    public function testCapturedContract(): void
    {
        $task = new Task(static fn (): Term => Term::constant(3));
        self::assertFalse($task->fiber->isStarted());
        self::assertSame([], $task->waiting);
    }

    public function testParkSubscribesOnceToRepeatedUnfinishedDependencies(): void
    {
        $parent = new Task(static fn (): Term => Term::constant(0));
        $child = new Task(static fn (): Term => Term::constant(7));
        $done = new Task(static fn (): Term => Term::constant(3));
        $done->result = Term::constant(3);
        $parent->waiting = [$child, $child, $done];
        self::assertFalse($parent->park());
        self::assertSame(1, $parent->unresolved);
        self::assertSame([$parent], array_values($child->waiters));
        self::assertSame([], $done->waiters);
    }

    public function testCompleteReleasesAParentOnlyAfterItsLastDependency(): void
    {
        $parent = new Task(static fn (): Term => Term::constant(0));
        $first = new Task(static fn (): Term => Term::constant(1));
        $last = new Task(static fn (): Term => Term::constant(2));
        $parent->waiting = [$first, $last];
        self::assertFalse($parent->park());
        $first->result = Term::constant(1);
        self::assertSame([], $first->complete());
        self::assertSame(1, $parent->unresolved);
        $last->result = Term::constant(2);
        self::assertSame([$parent], $last->complete());
        self::assertSame(0, $parent->unresolved);
        self::assertSame([], $first->waiters);
        self::assertSame([], $last->waiters);
        self::assertTrue($parent->park());
    }
}
