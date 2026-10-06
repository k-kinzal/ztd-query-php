<?php

declare(strict_types=1);

namespace Tests\Unit\Query;

use Deriver\Query\Budget;
use Deriver\Query\ParameterQuery as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ParameterQueryTest extends TestCase
{
    public function testScopeDefaultsToSourceOrigins(): void
    {
        self::assertSame('symbolic', (new Subject('f', 'x'))->scope()->mode);
    }

    public function testBudgetRetainsExplicitBounds(): void
    {
        $budget = new Budget(maxDepth:0);
        self::assertSame($budget, (new Subject('f', 'x', budget:$budget))->budget());
    }

}
