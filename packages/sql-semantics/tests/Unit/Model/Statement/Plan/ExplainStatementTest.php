<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Plan\SqlitePlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExplainStatement::class)]
final class ExplainStatementTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'EXPLAIN UPDATE t SET id=2 WHERE id=1'])]
    #[TestWith([Dialect::MySql, 'EXPLAIN UPDATE t SET id=2 WHERE id=1'])]
    #[TestWith([Dialect::Sqlite, 'EXPLAIN QUERY PLAN UPDATE t SET id=2 WHERE id=1'])]
    public function testRetainsTheWrappedMutation(Dialect $dialect, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertSame(StatementKind::Explain, $statement->kind);
        self::assertSame(StatementKind::Update, $statement->statement->kind);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement->statement);
        self::assertSame('id', $statement->statement->writes[0]->destinations()[0]->column()->columnBinding()?->column->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithStatementReplacesTheOperationImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('EXPLAIN SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        $changed = $statement->withStatement($binder->bind('SELECT 2'));
        self::assertSame('EXPLAIN SELECT 1', $statement->toString());
        self::assertSame('EXPLAIN SELECT 2', $changed->toString());
        self::assertInstanceOf(BoundSelect::class, $changed->statement);
    }

    public function testWithOptionsChangesThePlanRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('EXPLAIN SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        $changed = $statement->withOptions(new MySqlPlan(MySqlFormat::Json));
        self::assertSame('EXPLAIN FORMAT = JSON SELECT 1', $changed->toString());
        self::assertSame('EXPLAIN SELECT 1', $statement->toString());
    }

    public function testWithOptionsRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('EXPLAIN SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(SqlitePlan::QueryPlan);
    }
}
