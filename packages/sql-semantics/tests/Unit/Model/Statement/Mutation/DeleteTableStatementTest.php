<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeleteTableStatement::class)]
#[Medium]
final class DeleteTableStatementTest extends TestCase
{
    public function testBindsMySqlModifiersOrderingAndPagination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DELETE LOW_PRIORITY QUICK IGNORE FROM t WHERE id=1 ORDER BY id LIMIT 1');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        self::assertTrue($statement->lowPriority);
        self::assertTrue($statement->quick);
        self::assertTrue($statement->ignore);
        self::assertCount(1, $statement->orderBy);
        self::assertSame('1', $statement->limit?->spelling());
        self::assertSame(StatementKind::Delete, $statement->kind);
        self::assertSame('DELETE LOW_PRIORITY QUICK IGNORE FROM `t` WHERE (`id` = 1) ORDER BY `id` ASC LIMIT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAffectedTablesNamesTheSingleTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE FROM t WHERE id=1 RETURNING id');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        self::assertSame([$statement->target], $statement->affectedTables());
        self::assertSame('t', $statement->target->declaration->name);
        self::assertSame(['id'], array_column($statement->resultColumns(), 'name'));
    }

    public function testWithOriginPreservesTheTargetAndModifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DELETE QUICK FROM t ORDER BY id LIMIT 2');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->orderBy, $copy->orderBy);
        self::assertSame($statement->limit, $copy->limit);
        self::assertTrue($copy->quick);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithWhereReplacesThePredicateImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE FROM t WHERE id=1');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        $changed = $statement->withWhere(Expression::binary('>', Expression::reference(['id'], Dialect::Sqlite), Expression::literal(5, Dialect::Sqlite)));
        self::assertSame('DELETE FROM "main"."t" WHERE ("id" > 5)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('DELETE FROM "main"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withWhere(null)));
        self::assertSame('DELETE FROM "main"."t" WHERE ("id" = 1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($statement->target->declaration, $changed->target->declaration);
    }
}
