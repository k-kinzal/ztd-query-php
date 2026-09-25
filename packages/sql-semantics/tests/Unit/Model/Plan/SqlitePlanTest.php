<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\SqlitePlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqlitePlan::class)]
#[Medium]
final class SqlitePlanTest extends TestCase
{
    public function testRepresentsBothSqlitePlanReports(): void
    {
        self::assertSame(['EXPLAIN', 'EXPLAIN QUERY PLAN'], array_column(SqlitePlan::cases(), 'value'));
    }

    #[TestWith([SqlitePlan::Bytecode])]
    #[TestWith([SqlitePlan::QueryPlan])]
    public function testDialectIsSqliteForEveryReport(SqlitePlan $plan): void
    {
        self::assertSame(Dialect::Sqlite, $plan->dialect());
    }

    #[TestWith(['EXPLAIN SELECT 1', SqlitePlan::Bytecode])]
    #[TestWith(['EXPLAIN QUERY PLAN SELECT 1', SqlitePlan::QueryPlan])]
    public function testClassifiesTheRequestedReport(string $sql, SqlitePlan $plan): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertSame($plan, $statement->options);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
    }
}
