<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep;

#[CoversClass(Pending::class)]
#[UsesClass(SliceStep::class)]
final class PendingTest extends TestCase
{
    public function testNeedingStartsAPathThatHasGoneNowhere(): void
    {
        $path = Pending::needing(['sql' => true]);

        self::assertSame([], $path->steps);
        self::assertSame(['sql' => true], $path->needs);
        self::assertFalse($path->truncated);
        self::assertFalse($path->exhausted);
    }

    public function testThroughTakesOneStepFurtherBack(): void
    {
        $step = new SliceStep(new Expr\Variable('a'));

        $path = Pending::needing(['sql' => true])->through($step, ['base' => true]);

        self::assertSame([$step], $path->steps);
        self::assertSame(['base' => true], $path->needs);
    }

    public function testThenJoinsAPathThatStartedWhereThisOneStands(): void
    {
        $near = new SliceStep(new Expr\Variable('a'));
        $far = new SliceStep(new Expr\Variable('b'));

        $path = Pending::needing(['x' => true])->through($near, ['y' => true])
            ->then((new Pending([$far], ['z' => true], true, true)));

        self::assertSame([$near, $far], $path->steps);
        self::assertSame(['z' => true], $path->needs);
        self::assertTrue($path->truncated);
        self::assertTrue($path->exhausted);
    }

    public function testCutAndExhaustMarkThePath(): void
    {
        $path = Pending::needing(['x' => true]);

        self::assertTrue($path->cut()->truncated);
        self::assertFalse($path->cut()->exhausted);
        self::assertTrue($path->exhaust()->exhausted);
        self::assertFalse($path->exhaust()->truncated);
    }

    public function testExhaustMarksThePathStoppedByTheBudgetAndKeepsEverythingElse(): void
    {
        $step = new SliceStep(new Expr\Variable('a'));

        $cut = (new Pending([$step], ['x' => true], true))->exhaust();
        $whole = (new Pending([$step], ['y' => true]))->exhaust();

        self::assertTrue($cut->exhausted);
        self::assertTrue($cut->truncated);
        self::assertSame([$step], $cut->steps);
        self::assertSame(['x' => true], $cut->needs);
        self::assertTrue($whole->exhausted);
        self::assertFalse($whole->truncated);
        self::assertSame(['y' => true], $whole->needs);
        self::assertTrue($whole->isDone());
    }

    public function testIsDoneOnceNothingIsNeededOrTheBudgetStoppedIt(): void
    {
        self::assertTrue(Pending::needing([])->isDone());
        self::assertFalse(Pending::needing(['x' => true])->isDone());
        self::assertTrue(Pending::needing(['x' => true])->exhaust()->isDone());
    }

    public function testForwardTurnsTheStepsIntoTheOrderTheyRun(): void
    {
        $near = new SliceStep(new Expr\Variable('a'));
        $far = new SliceStep(new Expr\Variable('b'));

        self::assertSame([$far, $near], (new Pending([$near, $far], []))->forward());
    }

    public function testSignatureTellsPathsApartByWhatTheyNeedAndWhatTheyPassedThrough(): void
    {
        $step = new SliceStep(new Expr\Variable('a'));

        self::assertSame(
            (new Pending([$step], ['b' => true, 'a' => true]))->signature(),
            (new Pending([$step], ['a' => true, 'b' => true]))->signature(),
        );
        self::assertNotSame(
            (new Pending([$step], ['a' => true]))->signature(),
            (new Pending([], ['a' => true]))->signature(),
        );
    }

    public function testGatherKeepsEveryPathAsAnAlternativeOfOneStep(): void
    {
        $a = new SliceStep(new Expr\Variable('a'));
        $b = new SliceStep(new Expr\Variable('b'));

        $gathered = Pending::gather([new Pending([$a], ['x' => true]), new Pending([$b], ['y' => true], true)]);

        self::assertCount(1, $gathered->steps);
        self::assertSame([[$a], [$b]], $gathered->steps[0]->alternatives);
        self::assertSame(['x' => true, 'y' => true], $gathered->needs);
        self::assertTrue($gathered->truncated);
        self::assertFalse($gathered->exhausted);
    }
}
