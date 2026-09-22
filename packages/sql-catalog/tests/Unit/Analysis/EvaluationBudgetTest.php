<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EvaluationBudget;

#[CoversClass(EvaluationBudget::class)]
final class EvaluationBudgetTest extends TestCase
{
    public function testSpendReportsWhenTheBudgetRunsOut(): void
    {
        $budget = new EvaluationBudget(1);
        self::assertTrue($budget->spend());
        self::assertFalse($budget->spend());
    }

    public function testIsExhaustedOnceMoreWasSpentThanAllowed(): void
    {
        $budget = new EvaluationBudget(1);
        $budget->spend();
        self::assertFalse($budget->isExhausted());
        $budget->spend();
        self::assertTrue($budget->isExhausted());
    }

    public function testIsSpentOnlyOnceFourTimesTheStepBudgetWasSpent(): void
    {
        $budget = new EvaluationBudget(2);
        self::assertFalse($budget->isSpent());

        array_map(static fn (int $step): bool => $budget->spend(), range(1, 8));
        self::assertSame(8, $budget->spent());
        self::assertTrue($budget->isExhausted());
        self::assertFalse($budget->isSpent());

        $budget->spend();
        self::assertTrue($budget->isSpent());
    }

    public function testIsSpentIsClearedByAReset(): void
    {
        $budget = new EvaluationBudget(1);
        array_map(static fn (int $step): bool => $budget->spend(), range(1, 5));
        self::assertTrue($budget->isSpent());

        $budget->reset();
        self::assertFalse($budget->isSpent());
    }

    public function testTheReadingAllowanceIsFourTimesTheSearch(): void
    {
        self::assertSame(4, EvaluationBudget::READING_ALLOWANCE);
    }

    public function testSpentCountsTheStepsTaken(): void
    {
        $budget = new EvaluationBudget();
        $budget->spend();
        $budget->spend();
        self::assertSame(2, $budget->spent());
    }

    public function testResetStartsANewFile(): void
    {
        $budget = new EvaluationBudget(1);
        $budget->spend();
        $budget->spend();
        $budget->reset();
        self::assertSame(0, $budget->spent());
        self::assertFalse($budget->isExhausted());
    }

    public function testTheDefaultsBoundStepsDepthAndLoopPasses(): void
    {
        $budget = new EvaluationBudget();

        self::assertSame(20000, $budget->maxSteps);
        self::assertSame(4, $budget->maxDepth);
        self::assertSame(2, $budget->maxLoopPasses);
    }
}
