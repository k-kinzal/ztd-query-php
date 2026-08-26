<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\ReturningProjection;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Shadow\Mutation\InsertMutation;

#[UsesClass(InsertMutation::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(ReturningProjection::class)]
#[CoversClass(RewritePlan::class)]
final class RewritePlanTest extends TestCase
{
    public function testKindSqlKindPlanHoldsSqlKindAndMutation(): void
    {
        $mutation = new InsertMutation('users');
        $plan = new RewritePlan('SELECT 1', QueryKind::READ, $mutation);

        self::assertSame('SELECT 1', $plan->sql());
        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertSame($mutation, $plan->mutation());
    }

    public function testMutationPlanWithoutMutation(): void
    {
        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        self::assertNull($plan->mutation());
    }

    public function testAffectedRowsModeReturningProjectionAffectedRowsModePlanCarriesReturningAndAffectedRowsMetadata(): void
    {
        $projection = ReturningProjection::fromItems([['source' => 'id', 'output' => null]]);
        $plan = new RewritePlan(
            'SELECT 1 AS id',
            QueryKind::WRITE_SIMULATED,
            new InsertMutation('users'),
            $projection,
            AffectedRowsMode::Matched,
        );

        self::assertSame($projection, $plan->returningProjection());
        self::assertSame(AffectedRowsMode::Matched, $plan->affectedRowsMode());
    }
}
