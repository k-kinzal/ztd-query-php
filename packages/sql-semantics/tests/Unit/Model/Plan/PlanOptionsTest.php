<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Plan\PlanOptions;
use SqlSemantics\Model\Plan\PostgreSqlPlan;
use SqlSemantics\Model\Plan\SqlitePlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PlanOptions::class)]
#[Medium]
final class PlanOptionsTest extends TestCase
{
    /**
     * @param class-string<PlanOptions> $class
     */
    #[TestWith([Dialect::MySql, 'EXPLAIN SELECT 1', MySqlPlan::class])]
    #[TestWith([Dialect::PostgreSql, 'EXPLAIN SELECT 1', PostgreSqlPlan::class])]
    #[TestWith([Dialect::Sqlite, 'EXPLAIN QUERY PLAN SELECT 1', SqlitePlan::class])]
    public function testDialectMatchesTheBindingDialectOfEveryPlanOption(Dialect $dialect, string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertInstanceOf($class, $statement->options);
        self::assertSame($dialect, $statement->options->dialect());
    }
}
