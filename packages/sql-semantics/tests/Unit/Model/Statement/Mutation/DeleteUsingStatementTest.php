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
use SqlSemantics\Model\Statement\Mutation\DeleteUsingStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeleteUsingStatement::class)]
#[Medium]
final class DeleteUsingStatementTest extends TestCase
{
    public function testBindsTheTargetSeparatelyFromTheAdditionalInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id RETURNING t.id');
        self::assertInstanceOf(DeleteUsingStatement::class, $statement);
        self::assertSame('t', $statement->target->declaration->name);
        self::assertInstanceOf(TableReference::class, $statement->using);
        self::assertSame('s', $statement->using->declaration->name);
        self::assertSame(StatementKind::Delete, $statement->kind);
        self::assertSame(['id'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('DELETE FROM "public"."t" USING "public"."s" WHERE ("t"."id" = "s"."id") RETURNING "t"."id" AS "id"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAffectedTablesExcludesTheUsingInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
        self::assertInstanceOf(DeleteUsingStatement::class, $statement);
        self::assertSame([$statement->target], $statement->affectedTables());
        self::assertNotContains($statement->using, $statement->affectedTables());
    }

    public function testWithOriginPreservesBothInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
        self::assertInstanceOf(DeleteUsingStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->using, $copy->using);
        self::assertSame($statement->where, $copy->where);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithWhereReplacesThePredicateImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
        self::assertInstanceOf(DeleteUsingStatement::class, $statement);
        $changed = $statement->withWhere(Expression::binary('<', Expression::reference(['t', 'id'], Dialect::PostgreSql), Expression::reference(['s', 'id'], Dialect::PostgreSql)));
        self::assertSame('DELETE FROM "public"."t" USING "public"."s" WHERE ("t"."id" < "s"."id")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('DELETE FROM "public"."t" USING "public"."s"', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withWhere(null)));
        self::assertSame('=', $statement->where?->spelling());
    }
}
