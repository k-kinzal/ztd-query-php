<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatementOperands::class)]
#[Medium]
final class StatementOperandsTest extends TestCase
{
    public function testOutputsAcceptsOrderedProjectionsOfTheStatementDialect(): void
    {
        StatementOperands::outputs([new OutputColumn(0, 'a', Expression::literal(1, Dialect::MySql)), new OutputColumn(1, 'b', Expression::literal(2, Dialect::MySql))], Dialect::MySql);
        StatementOperands::outputs([], Dialect::MySql, true);
        $this->addToAssertionCount(1);
    }

    public function testOutputsRejectsAPositionThatDoesNotMatchItsOrdinal(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Result positions and expression dialects must agree with the statement.');
        StatementOperands::outputs([new OutputColumn(1, 'a', Expression::literal(1, Dialect::PostgreSql))], Dialect::PostgreSql);
    }

    public function testOutputsRejectsAnExpressionOfAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        StatementOperands::outputs([new OutputColumn(0, 'a', Expression::literal(1, Dialect::Sqlite))], Dialect::PostgreSql);
    }

    public function testOutputsRejectsAMySqlReturningProjection(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('MySQL mutations do not have a RETURNING projection.');
        StatementOperands::outputs([new OutputColumn(0, 'a', Expression::literal(1, Dialect::MySql))], Dialect::MySql, true);
    }

    public function testExpressionsSkipsAbsentOperandsAndRejectsAnotherDialect(): void
    {
        StatementOperands::expressions([null, Expression::literal(1, Dialect::Sqlite)], Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A statement expression must use its statement dialect.');
        StatementOperands::expressions([null, Expression::literal(1, Dialect::MySql)], Dialect::Sqlite);
    }

    public function testCtesAcceptsAnAbsentClauseAndRejectsAForeignDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertInstanceOf(BoundSelect::class, $statement);
        StatementOperands::ctes(null, Dialect::MySql);
        StatementOperands::ctes($statement->ctes, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A CTE must use its enclosing statement dialect.');
        StatementOperands::ctes($statement->ctes, Dialect::MySql);
    }

    public function testRelationAcceptsAnAbsentInputAndTheOwningDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t JOIN (SELECT 1 AS x) d ON TRUE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        StatementOperands::relation(null, Dialect::MySql);
        StatementOperands::relation($statement->from, Dialect::PostgreSql);
        $this->addToAssertionCount(1);
    }

    public function testRelationRejectsAnOnlyReferenceOutsidePostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM ONLY t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('ONLY table references require PostgreSQL.');
        StatementOperands::relation($statement->from, Dialect::Sqlite);
    }

    public function testRelationRejectsDeclaredColumnsOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A relation declaration must use the statement dialect.');
        StatementOperands::relation($statement->from, Dialect::Sqlite);
    }

    public function testRelationChecksNestedDerivedInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t JOIN (SELECT 1 AS x) d ON TRUE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        StatementOperands::relation($statement->from, Dialect::MySql);
    }

    public function testRelationRejectsIndexHintsOutsideMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER, KEY k (id))')))->bind('SELECT id FROM t USE INDEX (k)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Index hints require MySQL.');
        StatementOperands::relation($statement->from, Dialect::Sqlite);
    }
}
