<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRelationStatement::class)]
#[Medium]
final class AlterRelationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame(Kind\RelationKind::Table, $statement->relationKind);
        self::assertSame(['t'], $statement->name->parts);
        self::assertCount(2, $statement->actions);
        self::assertTrue($statement->ifExists);
        self::assertTrue($statement->only);
        self::assertSame('ALTER TABLE IF EXISTS ONLY "t" SET LOGGED, CLUSTER ON "t_pkey"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['public', 't']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['t']), $statement->name);
        self::assertEquals(new QualifiedName(['public', 't']), $changed->name);
        self::assertStringContainsString('ONLY "public"."t"', $changed->toString());
    }

    public function testWithActionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $changed = $statement->withActions([new \SqlSemantics\Model\Definition\Relation\Storage\SetTablespace('fast')]);
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->actions, $statement->actions);
        self::assertEquals([new \SqlSemantics\Model\Definition\Relation\Storage\SetTablespace('fast')], $changed->actions);
        self::assertStringContainsString('SET TABLESPACE "fast"', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER TABLE ONLY', $changed->toString());
    }

    public function testWithOnlyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE IF EXISTS ONLY t SET LOGGED, CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $changed = $statement->withOnly(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->only);
        self::assertEquals(false, $changed->only);
        self::assertStringContainsString('IF EXISTS "t"', $changed->toString());
    }

    public function testRejectsAPartitionActionAmongOthers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET LOGGED');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withActions([new \SqlSemantics\Model\Definition\Relation\Partition\DetachPartition(new QualifiedName(['p'])), ...$statement->actions]);
    }

    public function testRejectsOnlyForAnIndex(): void
    {
        $this->expectException(InvalidStructure::class);
        new AlterRelationStatement((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin, Kind\RelationKind::Index, new QualifiedName(['ix']), [new \SqlSemantics\Model\Definition\Relation\Storage\SetTablespace('fast')], only: true);
    }
}
