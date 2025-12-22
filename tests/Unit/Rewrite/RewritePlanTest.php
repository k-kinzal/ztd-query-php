<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use PHPUnit\Framework\TestCase;

final class RewritePlanTest extends TestCase
{
    public function testPlanHoldsSqlKindAndMutation(): void
    {
        $mutation = new InsertMutation('users');
        $plan = new RewritePlan('SELECT 1', QueryKind::READ, $mutation);

        $this->assertSame('SELECT 1', $plan->sql());
        $this->assertSame(QueryKind::READ, $plan->kind());
        $this->assertSame($mutation, $plan->mutation());
    }

    public function testPlanWithoutMutation(): void
    {
        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        $this->assertNull($plan->mutation());
    }
}
