<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
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
        self::assertSame('ALTER VIEW IF EXISTS "v" RENAME COLUMN "a" TO "b"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
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
        self::assertStringContainsString('ALTER MATERIALIZED VIEW', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['w']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['v']), $statement->name);
        self::assertEquals(new QualifiedName(['w']), $changed->name);
        self::assertStringContainsString('"w" RENAME', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithColumnReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withColumn('c');
        self::assertNotSame($statement, $changed);
        self::assertEquals('a', $statement->column);
        self::assertEquals('c', $changed->column);
        self::assertStringContainsString('COLUMN "c"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withNewName('d');
        self::assertNotSame($statement, $changed);
        self::assertEquals('b', $statement->newName);
        self::assertEquals('d', $changed->newName);
        self::assertStringContainsString('TO "d"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW IF EXISTS v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER VIEW "v"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOnlyAppliesToTables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE t RENAME a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        self::assertSame('ALTER TABLE ONLY "t" RENAME COLUMN "a" TO "b"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOnly(true)));
    }

    public function testRejectsASequence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME a TO b');
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRelationKind(Kind\RelationKind::Sequence);
    }

    public function testDefaultsToAPlainRenameOfAView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME COLUMN a TO b', strict: false);
        self::assertInstanceOf(RenameRelationColumnStatement::class, $statement);
        $rebuilt = new RenameRelationColumnStatement($statement->origin, Kind\RelationKind::View, new QualifiedName(['v']), 'a', 'b');
        self::assertFalse($rebuilt->ifExists);
        self::assertFalse($rebuilt->only);
        self::assertSame('ALTER VIEW "v" RENAME COLUMN "a" TO "b"', (new \SqlSemantics\SimpleSerializer())->serialize($rebuilt));
    }

    public function testRejectsOnlyOnAView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME COLUMN a TO b', strict: false);
        $this->expectExceptionObject(new InvalidStructure('ONLY applies to tables and foreign tables.'));
        new RenameRelationColumnStatement($statement->origin, Kind\RelationKind::View, new QualifiedName(['v']), 'a', 'b', only: true);
    }

    public function testRejectsAnOverlongName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME COLUMN a TO b', strict: false);
        $this->expectException(InvalidStructure::class);
        new RenameRelationColumnStatement($statement->origin, Kind\RelationKind::View, new QualifiedName(['a', 'b', 'c', 'd']), 'a', 'b');
    }

    #[TestWith(['', 'b'])]
    #[TestWith(['a', ''])]
    public function testRejectsAnEmptyColumnName(string $column, string $newName): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER VIEW v RENAME COLUMN a TO b', strict: false);
        $this->expectExceptionObject(new InvalidStructure('A catalog object identifier cannot be empty.'));
        new RenameRelationColumnStatement($statement->origin, Kind\RelationKind::View, new QualifiedName(['v']), $column, $newName);
    }
}
