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

    public function testTheDefaultsBoundDepthAndLoopPasses(): void
    {
        $budget = new EvaluationBudget();
        self::assertSame(4, $budget->maxDepth);
        self::assertSame(2, $budget->maxLoopPasses);
    }
}
