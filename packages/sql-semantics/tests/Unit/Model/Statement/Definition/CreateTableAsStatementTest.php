<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Definition\CreateTableAsStatement;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(CreateTableAsStatement::class)]
#[Medium]
final class CreateTableAsStatementTest extends TestCase
{
    public function testBindsTheDeclaredColumnsAndDataPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE u(a) AS SELECT 1 WITH NO DATA');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['u'], $statement->name->parts);
        self::assertSame(['a'], $statement->columns);
        self::assertFalse($statement->withData);
        self::assertFalse($statement->ifNotExists);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertInstanceOf(BoundSelect::class, $statement->query);
        self::assertSame('CREATE TABLE "u"("a") AS SELECT 1 WITH NO DATA', (new SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheQueryAndDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TABLE u AS SELECT 1 AS id');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->query, $copy->query);
        self::assertSame($statement->name, $copy->name);
        self::assertTrue($copy->withData);
        self::assertSame('CREATE TABLE "u" AS SELECT 1 AS "id"', (new SimpleSerializer())->serialize($copy));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testKeepsTheMySqlDuplicatePolicyThroughStructuralSerialization(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE u (a INT)'));
        $ignore = $binder->bind('CREATE TABLE t IGNORE AS SELECT a FROM u');
        $replace = $binder->bind('CREATE TEMPORARY TABLE IF NOT EXISTS t ENGINE = InnoDB REPLACE SELECT a FROM u');
        self::assertInstanceOf(CreateTableAsStatement::class, $ignore);
        self::assertInstanceOf(CreateTableAsStatement::class, $replace);
        self::assertSame(DuplicateRows::Ignore, $ignore->duplicates);
        self::assertSame(DuplicateRows::Replace, $replace->duplicates);
        self::assertSame('CREATE TABLE `t` IGNORE AS SELECT `a` AS `a` FROM `u`', (new SimpleSerializer())->serialize($ignore));
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS `t` ENGINE `InnoDB` REPLACE AS SELECT `a` AS `a` FROM `u`', (new SimpleSerializer())->serialize($replace));
        $rebound = $binder->bind((new SimpleSerializer())->serialize($replace));
        self::assertInstanceOf(CreateTableAsStatement::class, $rebound);
        self::assertSame(DuplicateRows::Replace, $rebound->duplicates);
        self::assertTrue($rebound->ifNotExists);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testLeavesTheDuplicatePolicyUnsetWithoutIgnoreOrReplace(string $release): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE u (a INT)')))->bind('CREATE TABLE t AS SELECT a FROM u');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertNull($statement->duplicates);
    }

    public function testWithDuplicatesReplacesThePolicyAndLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE u (a INT)')))->bind('CREATE TABLE t IGNORE AS SELECT a FROM u');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        $changed = $statement->withDuplicates(DuplicateRows::Replace);
        self::assertNotSame($statement, $changed);
        self::assertSame(DuplicateRows::Ignore, $statement->duplicates);
        self::assertSame(DuplicateRows::Replace, $changed->duplicates);
        self::assertSame('CREATE TABLE `t` REPLACE AS SELECT `a` AS `a` FROM `u`', $changed->toString());
        self::assertNull($changed->withDuplicates(null)->duplicates);
    }

    public function testRejectsADuplicatePolicyOutsideMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE u AS SELECT 1 AS id');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTableAsStatement($statement->origin, $statement->name, $statement->query, duplicates: DuplicateRows::Ignore);
    }

    public function testRejectsAMySqlQueryThatLocksAStoredTableForUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind('CREATE TABLE t AS SELECT a FROM u FOR SHARE');
        $locked = $binder->bind('SELECT a FROM u FOR UPDATE');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $locked);
        self::assertSame('CREATE TABLE `t` AS SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE', $statement->withDuplicates(null)->toString());
        $this->expectException(InvalidStructure::class);
        new CreateTableAsStatement($statement->origin, $statement->name, $locked);
    }

    public function testKeepsAPostgreSqlQueryThatLocksForUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind('CREATE TABLE t AS SELECT a FROM u FOR UPDATE');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame('CREATE TABLE "t" AS SELECT "a" AS "a" FROM "public"."u" FOR UPDATE', (new SimpleSerializer())->serialize($statement));
    }
}
