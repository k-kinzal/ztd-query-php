<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Search;

use Deriver\Deriver\Evaluator\Search\Search;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SearchTest extends TestCase
{
    public function testResolveSharesCompletedDependencies(): void
    {
        $search = new Search();
        $calls = 0;
        $evaluate = static function () use (&$calls): Term {
            $calls++;
            return Term::constant(12);
        };
        $cycle = static fn (): Term => Term::opaque('CYCLE');
        self::assertSame($search->resolve('x', $evaluate, $cycle), $search->resolve('x', $evaluate, $cycle));
        self::assertSame(1, $calls);
    }

    public function testResolveClosesACycleWithoutConfusingAWaitingSibling(): void
    {
        $search = new Search();
        $cycle = static fn (): Term => Term::opaque('CYCLE');
        $result = $search->resolve('a', static fn (): Term => $search->resolve('b', static fn (): Term => $search->resolve('a', static fn (): Term => Term::constant(99), $cycle), $cycle), $cycle);
        self::assertSame('CYCLE', $result->literal);
        $values = $search->all([static fn (): Term => $search->resolve('shared', static fn (): Term => Term::constant(3), $cycle), static fn (): Term => $search->resolve('shared', static fn (): Term => Term::constant(99), $cycle)]);
        self::assertSame([3, 3], array_map(static fn (Term $value): int|float|string|bool|null => $value->literal, $values));
    }

    public function testAllLetsShortBranchesFinishBeforeLongBranches(): void
    {
        $search = new Search();
        $events = [];
        $values = $search->all([
            static function () use ($search, &$events): Term {
                for ($step = 0; $step < 4; $step++) {
                    $events[] = 'slow';
                    $search->checkpoint();
                }
                $events[] = 'slow-finished';
                return Term::constant(4);
            },
            static function () use (&$events): Term {
                $events[] = 'fast-finished';
                return Term::constant(1);
            },
        ]);
        self::assertSame(['slow', 'fast-finished', 'slow', 'slow', 'slow', 'slow-finished'], $events);
        self::assertSame([4, 1], array_map(static fn (Term $value): int|float|string|bool|null => $value->literal, $values));
    }

    public function testCycleThroughALaterSiblingClosesWithoutDiscardingOtherBranches(): void
    {
        $search = new Search();
        $residual = Term::opaque('CYCLE');
        $cycle = static fn (): Term => $residual;
        $unused = static fn (): Term => Term::constant(99);
        $values = $search->all([
            static fn (): Term => $search->resolve('a', static fn (): Term => $search->all([
                static fn (): Term => Term::constant(1),
                static fn (): Term => $search->resolve('b', $unused, $cycle),
            ])[1], $cycle),
            static fn (): Term => $search->resolve('b', static fn (): Term => $search->resolve('a', $unused, $cycle), $cycle),
            static fn (): Term => Term::constant(7),
        ]);

        self::assertSame($residual, $values[0]);
        self::assertSame($residual, $values[1]);
        self::assertSame(7, $values[2]->literal);
        self::assertSame(8, $search->resolve('independent', static fn (): Term => Term::constant(8), $cycle)->literal);
    }

    public function testCheckpointLeavesAnIdleSearchUsable(): void
    {
        $search = new Search();
        $search->checkpoint();
        self::assertSame([], $search->all([]));
    }

    public function testEnqueueDefersWorkUntilTheSearchAdvances(): void
    {
        $search = new Search();
        $task = $search->enqueue(static fn (): Term => Term::constant(7));
        self::assertFalse($task->fiber->isStarted());
        $search->advance();
        self::assertSame(7, $task->result?->literal);
    }

    public function testAwaitResumesAParentOnlyAfterItsChildFinishes(): void
    {
        $search = new Search();
        $parent = $search->enqueue(static function () use ($search): Term {
            $child = $search->enqueue(static function () use ($search): Term {
                $search->checkpoint();
                return Term::constant(8);
            });
            return $search->await($child);
        });
        self::assertSame(8, $search->await($parent)->literal);
        self::assertSame([], $parent->waiting);
    }

    public function testAdvanceRunsOneRunnableBranch(): void
    {
        $search = new Search();
        $first = $search->enqueue(static fn (): Term => Term::constant(1));
        $second = $search->enqueue(static fn (): Term => Term::constant(2));
        $search->advance();
        self::assertSame(1, $first->result?->literal);
        self::assertFalse($second->fiber->isStarted());
    }

    public function testSharedChildWakesEveryParentExactlyOnce(): void
    {
        $search = new Search();
        $evaluations = 0;
        $resumes = 0;
        $branch = static function () use ($search, &$evaluations, &$resumes): Term {
            $value = $search->resolve('shared', static function () use ($search, &$evaluations): Term {
                $evaluations++;
                $search->checkpoint();
                return Term::constant(7);
            }, static fn (): Term => Term::opaque('CYCLE'));
            $resumes++;
            $search->checkpoint();
            return $value;
        };
        $values = $search->all(array_fill(0, 64, $branch));
        self::assertSame(array_fill(0, 64, 7), array_map(static fn (Term $value) => $value->literal, $values));
        self::assertSame(1, $evaluations);
        self::assertSame(64, $resumes);
    }

    public function testRepeatedDependencyInAJoinDoesNotLeaveTheParentParked(): void
    {
        $search = new Search();
        $parent = $search->enqueue(static function () use ($search): Term {
            $child = $search->enqueue(static fn (): Term => Term::constant(5));
            return Term::array($search->all([
                static fn (): Term => $search->await($child),
                static fn (): Term => $search->await($child),
            ]));
        });
        self::assertSame([5, 5], $search->await($parent)->native());
        self::assertSame([], $parent->waiting);
        self::assertSame(0, $parent->unresolved);
    }
}
