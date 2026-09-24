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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationColumnStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameRelationColumnStatement::class)]
#[Medium]
final class RenameRelationColumnStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        self::assertSame(Kind\RelationKind::View, $statement->relationKind);
        self::assertSame(['v'], $statement->name->parts);
        self::assertSame('a', $statement->column);
        self::assertSame('b', $statement->newName);
        self::assertTrue($statement->ifExists);
        self::assertFalse($statement->only);
        self::assertSame('ALTER VIEW IF EXISTS "v" RENAME COLUMN "a" TO "b"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithRelationKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withRelationKind(Kind\RelationKind::MaterializedView);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\RelationKind::View, $statement->relationKind);
        self::assertEquals(Kind\RelationKind::MaterializedView, $changed->relationKind);
        self::assertStringContainsString('ALTER MATERIALIZED VIEW', $changed->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['w']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['v']), $statement->name);
        self::assertEquals(new QualifiedName(['w']), $changed->name);
        self::assertStringContainsString('"w" RENAME', $changed->toString());
    }

    public function testWithColumnReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withColumn('c');
        self::assertNotSame($statement, $changed);
        self::assertEquals('a', $statement->column);
        self::assertEquals('c', $changed->column);
        self::assertStringContainsString('COLUMN "c"', $changed->toString());
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withNewName('d');
        self::assertNotSame($statement, $changed);
        self::assertEquals('b', $statement->newName);
        self::assertEquals('d', $changed->newName);
        self::assertStringContainsString('TO "d"', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER VIEW "v"', $changed->toString());
    }

    public function testWithOnlyAppliesToTables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE t RENAME a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        self::assertSame('ALTER TABLE ONLY "t" RENAME COLUMN "a" TO "b"', $statement->withOnly(true)->toString());
    }

    public function testRejectsASequence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME a TO b');
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRelationKind(Kind\RelationKind::Sequence);
    }
}
