<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UpdateTableStatementTest extends TestCase
{
    public function testReturningDoesNotCreateAWherePredicate(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE OR IGNORE t SET id=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertNull($statement->where);
        self::assertSame(ConstraintResponse::Ignore, $statement->onViolation);
        self::assertSame('t', $statement->target->declaration->name);
        self::assertCount(1, $statement->affectedTables());
        self::assertSame('UPDATE OR IGNORE "main"."t" SET "id" = 1 RETURNING "id" AS "id"', $statement->toString());
    }

    public function testWithOriginKeepsTheTargetAndWrites(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::PostgreSql, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->target, $changed->target);
        self::assertSame($statement->writes, $changed->writes);
        self::assertSame('UPDATE "public"."t" SET "n" = 1 RETURNING "id" AS "id"', $changed->toString());
    }

    public function testAffectedTablesIsTheSingleTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertSame([$statement->target], $statement->affectedTables());
        self::assertSame('t', $statement->affectedTables()[0]->declaration->name);
    }

    public function testWithWhereAddsAndRemovesThePredicate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $changed = $statement->withWhere(\SqlSemantics\Model\Expression::binary('>', \SqlSemantics\Model\Expression::reference(['id'], Dialect::PostgreSql), \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql)));
        self::assertSame('UPDATE "public"."t" SET "n" = 1 WHERE ("id" > 1) RETURNING "id" AS "id"', $changed->toString());
        self::assertSame('id', $changed->where?->inputs()[0]->columnBinding()?->column->name);
        self::assertNull($statement->where);
        self::assertNull($changed->withWhere(null)->where);
    }

    public function testWithAssignmentsReplacesTheWrites(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind('UPDATE t SET n=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $other = $binder->bind('UPDATE t SET id=5, n=6');
        self::assertInstanceOf(UpdateTableStatement::class, $other);
        $changed = $statement->withAssignments($other->writes);
        self::assertSame('UPDATE "public"."t" SET "id" = 5, "n" = 6 RETURNING "id" AS "id"', $changed->toString());
        self::assertCount(2, $changed->writes);
        self::assertCount(1, $statement->writes);
        self::assertSame('UPDATE "public"."t" SET "n" = 1 RETURNING "id" AS "id"', $statement->toString());
    }
}
