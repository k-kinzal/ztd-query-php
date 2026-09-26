<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Query;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class QueryTest extends TestCase
{
    public function testScopePreservesTheSemanticContract(): void
    {
        self::assertContains(\Deriver\Api\Query\Query::class, class_implements(\Deriver\Api\Query\ReturnQuery::class));
    }
    public function testBudgetPreservesTheExplicitLimits(): void
    {
        $budget = new \Deriver\Api\Query\Budget(transfers: 7, partitions: 2);
        $query = new \Deriver\Api\Query\ReturnQuery('target', budget: $budget);
        self::assertSame($budget, $query->budget());
    }
}
