<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Mutation\UpdateFromStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdateFromStatement::class)]
#[Medium]
final class UpdateFromStatementTest extends TestCase
{
    public function testBindsTheTargetSeparatelyFromTheFromInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id RETURNING t.n');
        self::assertInstanceOf(UpdateFromStatement::class, $statement);
        self::assertSame('t', $statement->target->declaration->name);
        self::assertInstanceOf(TableReference::class, $statement->from);
        self::assertSame('s', $statement->from->declaration->name);
        self::assertSame(ConstraintResponse::Default, $statement->onViolation);
        self::assertSame(StatementKind::Update, $statement->kind);
        self::assertSame(['n'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('UPDATE "public"."t" SET "n" = "s"."n" FROM "public"."s" WHERE ("t"."id" = "s"."id") RETURNING "t"."n" AS "n"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAffectedTablesExcludesTheFromInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id');
        self::assertInstanceOf(UpdateFromStatement::class, $statement);
        self::assertSame([$statement->target], $statement->affectedTables());
        self::assertNotContains($statement->from, $statement->affectedTables());
    }

    public function testWithOriginPreservesInputsAndWrites(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id');
        self::assertInstanceOf(UpdateFromStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->from, $copy->from);
        self::assertSame($statement->writes, $copy->writes);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithWhereReplacesThePredicateImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)')))->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id');
        self::assertInstanceOf(UpdateFromStatement::class, $statement);
        $changed = $statement->withWhere(Expression::binary('<', Expression::reference(['t', 'id'], Dialect::PostgreSql), Expression::reference(['s', 'id'], Dialect::PostgreSql)));
        self::assertSame('UPDATE "public"."t" SET "n" = "s"."n" FROM "public"."s" WHERE ("t"."id" < "s"."id")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('UPDATE "public"."t" SET "n" = "s"."n" FROM "public"."s"', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withWhere(null)));
        self::assertSame('=', $statement->where?->spelling());
    }

    public function testWithAssignmentsReplacesTheWritesImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)'));
        $statement = $binder->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id');
        $other = $binder->bind('UPDATE t SET id=s.id FROM s');
        self::assertInstanceOf(UpdateFromStatement::class, $statement);
        self::assertInstanceOf(UpdateFromStatement::class, $other);
        $changed = $statement->withAssignments($other->writes);
        self::assertSame('UPDATE "public"."t" SET "id" = "s"."id" FROM "public"."s" WHERE ("t"."id" = "s"."id")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('id', $changed->writes[0]->destinations()[0]->column()->columnBinding()?->column->name);
        self::assertSame('n', $statement->writes[0]->destinations()[0]->column()->columnBinding()?->column->name);
    }
}
