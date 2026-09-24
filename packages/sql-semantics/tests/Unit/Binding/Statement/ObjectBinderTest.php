<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TableDropScope;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Definition\CreateTableAsStatement;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\Model\Statement\Definition\DropIndexStatement;
use SqlSemantics\Model\Statement\Definition\DropTableStatement;
use SqlSemantics\Model\Statement\Definition\DropTriggerStatement;
use SqlSemantics\Model\Statement\Definition\DropViewStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\ObjectBinder::class)]
#[Medium]
final class ObjectBinderTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE VIRTUAL TABLE v USING fts5(a)', CreateVirtualTableStatement::class])]
    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END', CreateSqliteTriggerStatement::class])]
    #[TestWith(['CREATE VIEW v AS SELECT a FROM t', CreateViewStatement::class])]
    #[TestWith(['CREATE TABLE u AS SELECT a FROM t', CreateTableAsStatement::class])]
    #[TestWith(['DROP TABLE IF EXISTS t', DropTableStatement::class])]
    public function testBindDispatchesByTheOuterSchemaOperation(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind($sql);
        self::assertInstanceOf($class, $statement);
    }

    public function testBindPrefersMaterializedViewsOverPlainViews(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
    }

    public function testDropReadsScopeExistenceAndDependencyPolicy(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $tables = $binder->bind('DROP TEMPORARY TABLE IF EXISTS a, b CASCADE');
        self::assertInstanceOf(DropTableStatement::class, $tables);
        self::assertSame(TableDropScope::Temporary, $tables->selection);
        self::assertTrue($tables->ifExists);
        self::assertSame(DropBehavior::Cascade, $tables->behavior);
        self::assertInstanceOf(DropViewStatement::class, $binder->bind('DROP VIEW v'));
        self::assertInstanceOf(DropIndexStatement::class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX i'));
        self::assertInstanceOf(DropTriggerStatement::class, (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TRIGGER IF EXISTS tr'));
    }

    #[TestWith(['DROP TABLE x.a.b.t'])]
    #[TestWith(['DROP VIEW x.a.b.v'])]
    #[TestWith(['DROP INDEX x.a.b.i'])]
    public function testDropRejectsAPostgreSqlNameBeyondCatalogSchemaAndObject(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::RelationName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testTableAsReadsDeclaredNamesStorageAndPopulation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE UNLOGGED TABLE IF NOT EXISTS u (x) WITH (fillfactor = 70) AS SELECT a FROM t WITH NO DATA');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['x'], $statement->columns);
        self::assertTrue($statement->ifNotExists);
        self::assertFalse($statement->withData);
    }

    public function testNameReadsTheQualifiedDeclarationTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind('CREATE TABLE main.u AS SELECT a FROM t');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['main', 'u'], $statement->name->parts);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindSpellsEveryObjectForm')]
    public function testBindSpellsEveryObjectForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindSpellsEveryObjectForm(): iterable
    {
        return [
            'drop table if exists t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'drop table if exists t', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE IF EXISTS "t"'],
            'drop view v (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'drop view v', 'SqlSemantics\\Model\\Statement\\Definition\\DropViewStatement => DROP VIEW "v"'],
            'drop index i (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'drop index i', 'SqlSemantics\\Model\\Statement\\Definition\\DropIndexStatement => DROP INDEX "i"'],
            'drop trigger tr (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'drop trigger tr', 'SqlSemantics\\Model\\Statement\\Definition\\DropTriggerStatement => DROP TRIGGER "tr"'],
            'DROP TABLE main.t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'DROP TABLE main.t', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE "main"."t"'],
            'create table u as select a from t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'create table u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u" AS SELECT "a" AS "a" FROM "main"."t"'],
            'CREATE TABLE IF NOT EXISTS u AS SELECT a FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE IF NOT EXISTS u AS SELECT a FROM t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE IF NOT EXISTS "u" AS SELECT "a" AS "a" FROM "main"."t"'],
            'create temp table if not exists u as select a from t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'create temp table if not exists u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TEMPORARY TABLE IF NOT EXISTS "u" AS SELECT "a" AS "a" FROM "main"."t"'],
            'CREATE TABLE u AS WITH x AS (SELECT 1) SELECT * FROM x (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u AS WITH x AS (SELECT 1) SELECT * FROM x', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u" AS WITH "x" AS (SELECT 1) SELECT "x"."?column?" AS "?column?" FROM "x"'],
            'alter table t add column b int (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'alter table t add column b int', 'SqlSemantics\\Model\\Statement\\Definition\\AddColumnStatement => ALTER TABLE "t" ADD COLUMN "b" "int"'],
            'create view v as select a from t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT)'], 'create view v as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateViewStatement => CREATE VIEW "v" AS SELECT "a" AS "a" FROM "main"."t"'],
            'drop table if exists t cascade (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'drop table if exists t cascade', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE IF EXISTS `t` CASCADE'],
            'drop temporary table t restrict (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'drop temporary table t restrict', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TEMPORARY TABLE `t` RESTRICT'],
            'drop view if exists v (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'drop view if exists v', 'SqlSemantics\\Model\\Statement\\Definition\\DropViewStatement => DROP VIEW IF EXISTS `v`'],
            'drop trigger tr (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'drop trigger tr', 'SqlSemantics\\Model\\Statement\\Definition\\DropTriggerStatement => DROP TRIGGER `tr`'],
            'drop index i on t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'drop index i on t', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableIndexStatement => DROP INDEX `i` ON `t` ALGORITHM = DEFAULT LOCK = DEFAULT'],
            'DROP TABLES t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'DROP TABLES t', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE `t`'],
            'create table u as select a from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create table u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE `u` AS SELECT `a` AS `a` FROM `t`'],
            'create table if not exists u select a from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create table if not exists u select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE IF NOT EXISTS `u` AS SELECT `a` AS `a` FROM `t`'],
            'CREATE TABLE u (SELECT a FROM t) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u (SELECT a FROM t)', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE `u` AS SELECT `a` AS `a` FROM `t`'],
            'create temporary table u as select a from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create temporary table u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TEMPORARY TABLE `u` AS SELECT `a` AS `a` FROM `t`'],
            'CREATE TABLE u AS TABLE t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u AS TABLE t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE `u` AS TABLE `t`'],
            'CREATE TABLE u AS VALUES ROW(1) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u AS VALUES ROW(1)', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE `u` AS VALUES ROW(1)'],
            'alter table t add column b int (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'alter table t add column b int', 'SqlSemantics\\Model\\Statement\\Definition\\MySql\\Table\\AlterTableStatement => ALTER TABLE `t` ADD COLUMN `b` integer'],
            'drop table if exists t cascade (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'drop table if exists t cascade', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE IF EXISTS "t" CASCADE'],
            'drop table t restrict (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'drop table t restrict', 'SqlSemantics\\Model\\Statement\\Definition\\DropTableStatement => DROP TABLE "t" RESTRICT'],
            'drop view v (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'drop view v', 'SqlSemantics\\Model\\Statement\\Definition\\DropViewStatement => DROP VIEW "v"'],
            'drop index if exists i (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'drop index if exists i', 'SqlSemantics\\Model\\Statement\\Definition\\DropIndexStatement => DROP INDEX IF EXISTS "i"'],
            'create table u as select a from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'create table u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u" AS SELECT "a" AS "a" FROM "public"."t"'],
            'CREATE TABLE u (x) AS SELECT a FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u (x) AS SELECT a FROM t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u"("x") AS SELECT "a" AS "a" FROM "public"."t"'],
            'create table if not exists u as select a from t with no data (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'create table if not exists u as select a from t with no data', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE IF NOT EXISTS "u" AS SELECT "a" AS "a" FROM "public"."t" WITH NO DATA'],
            'create temp table u as select a from t with data (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'create temp table u as select a from t with data', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TEMPORARY TABLE "u" AS SELECT "a" AS "a" FROM "public"."t"'],
            'CREATE TABLE u AS TABLE t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u AS TABLE t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u" AS TABLE "public"."t"'],
            'CREATE TABLE u AS VALUES (1) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'CREATE TABLE u AS VALUES (1)', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE TABLE "u" AS VALUES (1)'],
            'SELECT a INTO u FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'SELECT a INTO u FROM t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TABLE "u" FROM "public"."t"'],
            'alter table t add column b int (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'alter table t add column b int', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Relation\\AlterRelationStatement => ALTER TABLE "t" ADD COLUMN "b" integer'],
            'create unlogged table u as select a from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'create unlogged table u as select a from t', 'SqlSemantics\\Model\\Statement\\Definition\\CreateTableAsStatement => CREATE UNLOGGED TABLE "u" AS SELECT "a" AS "a" FROM "public"."t"'],
        ];
    }
}
