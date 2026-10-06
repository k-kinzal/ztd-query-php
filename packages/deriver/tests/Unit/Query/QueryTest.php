<?php

declare(strict_types=1);

namespace Tests\Unit\Query;

use Deriver\Query\Budget;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
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
