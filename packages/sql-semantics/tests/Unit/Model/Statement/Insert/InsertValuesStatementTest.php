<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertValuesStatementTest extends TestCase
{
    public function testRequiresRows(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES(1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new InsertValuesStatement($statement->origin, $statement->insertion, []);
    }

    public function testWithRowsPreservesTheOriginalAndRebindsTheInput(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES(1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $changed = $statement->withRows([[Expression::literal(2, Dialect::PostgreSql)]]);
        self::assertInstanceOf(Literal::class, $statement->rows[0][0]);
        self::assertInstanceOf(Literal::class, $changed->rows[0][0]);
        self::assertSame('1', $statement->rows[0][0]->text);
        self::assertSame('2', $changed->rows[0][0]->text);
        self::assertSame(StatementKind::Insert, $changed->kind);
        self::assertFalse(property_exists($changed, 'query'));
        self::assertSame('INSERT INTO "public"."t"("id") VALUES (2)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithRowsRejectsAReferenceOutsideTheBindingScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES(1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $this->expectException(\SqlSemantics\SemanticException::class);
        $statement->withRows([[Expression::reference(['missing'], Dialect::PostgreSql)]]);
    }

    public function testWithOriginKeepsTheRowsAndDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t(id) VALUES (1) RETURNING id');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::PostgreSql, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->rows, $changed->rows);
        self::assertSame($statement->outputs, $changed->outputs);
        self::assertSame('INSERT INTO "public"."t"("id") VALUES (1) RETURNING "id" AS "id"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithReturningReplacesOutputsAndKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('INSERT INTO t(id) VALUES (1) RETURNING id');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $changed = $statement->withReturning([new \SqlSemantics\Model\OutputColumn(0, 'twice', Expression::binary('*', Expression::reference(['n'], Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql)))]);
        self::assertSame('INSERT INTO "public"."t"("id") VALUES (1) RETURNING ("n" * 2) AS "twice"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('INSERT INTO "public"."t"("id") VALUES (1) RETURNING "id" AS "id"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('INSERT INTO "public"."t"("id") VALUES (1)', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withReturning([])));
    }
}
