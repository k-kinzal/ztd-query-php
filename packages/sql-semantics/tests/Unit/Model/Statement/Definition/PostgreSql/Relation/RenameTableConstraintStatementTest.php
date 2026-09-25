<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameTableConstraintStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameTableConstraintStatement::class)]
#[Medium]
final class RenameTableConstraintStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        self::assertSame(['app', 't'], $statement->table->parts);
        self::assertSame('c', $statement->constraint);
        self::assertSame('d', $statement->newName);
        self::assertTrue($statement->only);
        self::assertFalse($statement->ifExists);
        self::assertSame('ALTER TABLE ONLY "app"."t" RENAME CONSTRAINT "c" TO "d"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithTableReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $changed = $statement->withTable(new QualifiedName(['u']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 't']), $statement->table);
        self::assertEquals(new QualifiedName(['u']), $changed->table);
        self::assertStringContainsString('ONLY "u"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $changed = $statement->withConstraint('e');
        self::assertNotSame($statement, $changed);
        self::assertEquals('c', $statement->constraint);
        self::assertEquals('e', $changed->constraint);
        self::assertStringContainsString('CONSTRAINT "e"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $changed = $statement->withNewName('f');
        self::assertNotSame($statement, $changed);
        self::assertEquals('d', $statement->newName);
        self::assertEquals('f', $changed->newName);
        self::assertStringContainsString('TO "f"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertNotSame($statement, $changed);
        self::assertEquals(false, $statement->ifExists);
        self::assertEquals(true, $changed->ifExists);
        self::assertStringContainsString('IF EXISTS ONLY', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOnlyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d', strict: false);
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $changed = $statement->withOnly(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->only);
        self::assertEquals(false, $changed->only);
        self::assertStringContainsString('ALTER TABLE "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnEmptyConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ONLY app.t RENAME CONSTRAINT c TO d');
        self::assertInstanceOf(RenameTableConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withConstraint('');
    }
}
