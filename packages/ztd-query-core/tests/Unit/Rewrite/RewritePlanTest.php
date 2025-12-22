<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(InsertMutation::class)]
#[CoversClass(RewritePlan::class)]
final class RewritePlanTest extends TestCase
{
    public function testPlanHoldsSqlKindAndMutation(): void
    {
        $mutation = new InsertMutation('users');
        $plan = new RewritePlan('SELECT 1', QueryKind::READ, $mutation);

        self::assertSame('SELECT 1', $plan->sql());
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($mutation, $plan->mutation());
    }

    public function testPlanWithoutMutation(): void
    {
        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        self::assertNull($plan->mutation());
    }
}
