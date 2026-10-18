<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Request;

use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class QueryTest extends TestCase
{
    public function testScopePreservesTheSemanticContract(): void
    {
        self::assertContains(Query::class, class_implements(ReturnQuery::class));
    }
    public function testBudgetPreservesTheExplicitLimits(): void
    {
        $budget = new Budget(transfers: 7, partitions: 2);
        $query = new ReturnQuery('target', budget: $budget);
        self::assertSame($budget, $query->budget());
    }
}
