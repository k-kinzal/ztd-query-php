<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Table\CreateTableLikeStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Declarations;

#[CoversClass(Declarations::class)]
#[Medium]
final class DeclarationsTest extends TestCase
{
    public function testLikeKeepsTheTemplateRelationInsteadOfExpandingIt(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE original(id INT)'));
        $statement = $binder->bind('CREATE TEMPORARY TABLE IF NOT EXISTS copied LIKE original');
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS `copied` LIKE `original`', Declarations::like($statement)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateTableLikeStatement::class, $rebound);
        self::assertTrue($rebound->temporary);
        self::assertTrue($rebound->ifNotExists);
        self::assertSame('original', $rebound->template->declaration->name);
    }

    #[TestWith([Dialect::PostgreSql, 'CREATE UNLOGGED TABLE t (id INT PRIMARY KEY, n INT NOT NULL DEFAULT 1, CONSTRAINT c CHECK (n > 0))', 'CREATE UNLOGGED TABLE "public"."t"("id" integer NOT NULL, "n" integer NOT NULL DEFAULT 1, PRIMARY KEY("id"), CONSTRAINT "c" CHECK (("n" > 0)))'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TEMP TABLE IF NOT EXISTS t (id INT)', 'CREATE TEMPORARY TABLE IF NOT EXISTS "t"("id" integer)'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TEMP TABLE pg_temp.t (id INT)', 'CREATE TEMPORARY TABLE "t"("id" integer)'])]
    #[TestWith([Dialect::MySql, 'CREATE TEMPORARY TABLE t (id INT AUTO_INCREMENT, PRIMARY KEY (id), INDEX ix (id))', 'CREATE TEMPORARY TABLE `t`(`id` integer NOT NULL AUTO_INCREMENT, PRIMARY KEY(`id`), INDEX `ix`(`id`))'])]
    #[TestWith([Dialect::Sqlite, 'CREATE TEMP TABLE t (id INTEGER, n TEXT, PRIMARY KEY (id))', 'CREATE TEMPORARY TABLE "t"("id" "integer" NOT NULL, "n" "text", PRIMARY KEY("id"))'])]
    public function testTableWritesColumnsConstraintsIndexesAndPersistence(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame($expected, Declarations::table($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        self::assertSame(count($statement->definition->table->columns), count($rebound->definition->table->columns));
        self::assertSame($expected, $rebound->toString());
    }

    public function testTableFoldsTheSqliteAutoincrementPrimaryKeyIntoItsColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, n TEXT)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame('CREATE TABLE "main"."t"("id" "integer" NOT NULL PRIMARY KEY AUTOINCREMENT, "n" "text")', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith([Dialect::Sqlite, 'CREATE INDEX IF NOT EXISTS main.ix ON t (id COLLATE nocase ASC, n DESC) WHERE n > 0', 'CREATE INDEX IF NOT EXISTS "main"."ix" ON "t"("id" COLLATE "nocase" ASC, "n" DESC) WHERE ("n" > 0)'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ix ON ONLY t (id DESC NULLS LAST) INCLUDE (n) WHERE id > 0', 'CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS "ix" ON ONLY "public"."t"("id" DESC NULLS LAST) INCLUDE("n") WHERE ("id" > 0)'])]
    public function testIndexWritesKindTargetKeysAndPredicate(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        self::assertSame($expected, Declarations::index($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateIndexStatement::class, $rebound);
        self::assertSame($statement->index->definition->unique, $rebound->index->definition->unique);
        self::assertSame(count($statement->index->definition->elements), count($rebound->index->definition->elements));
        self::assertSame($expected, $rebound->toString());
    }

    public function testIndexWritesThePostgresAccessMethod(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)')))->bind('CREATE INDEX ix ON t USING btree (id)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        self::assertSame('btree', $statement->index->definition->method);
        self::assertSame('CREATE INDEX "ix" ON "public"."t" USING "btree"("id")', Declarations::index($statement)->toString());
    }

    public function testTableWritesTemplatesAtTheirPositionsAndExclusionsAfterConstraints(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE s(a INTEGER)'));
        $statement = $binder->bind('CREATE TABLE t (EXCLUDE (x WITH =), x INTEGER, LIKE s, CHECK (x > 0))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame('CREATE TABLE "public"."t"("x" integer, LIKE "s", CHECK (("x" > 0)), EXCLUDE("x" WITH =))', Declarations::table($statement)->toString());
    }

    public function testTableNameWritesATemporaryTableUnqualified(): void
    {
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMP TABLE a(x INT)', 'CREATE TABLE b(x INT)');
        self::assertSame([['a'], ['public', 'b']], array_map(Declarations::tableName(...), $postgres->tables));
        $sqlite = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TEMP TABLE a(x INT)', 'CREATE TABLE temp.b(x INT)');
        self::assertSame([['a'], ['temp', 'b']], array_map(Declarations::tableName(...), $sqlite->tables));
    }
}
