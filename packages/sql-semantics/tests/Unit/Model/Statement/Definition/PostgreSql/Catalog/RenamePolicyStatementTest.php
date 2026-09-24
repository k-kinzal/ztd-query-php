<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenamePolicyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenamePolicyStatement::class)]
#[Medium]
final class RenamePolicyStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        self::assertSame('owner_only', $statement->name);
        self::assertSame(['app', 'docs'], $statement->table->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame('owners', $statement->newName);
        self::assertSame('ALTER POLICY IF EXISTS "owner_only" ON "app"."docs" RENAME TO "owners"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $changed = $statement->withName('p');
        self::assertNotSame($statement, $changed);
        self::assertEquals('owner_only', $statement->name);
        self::assertEquals('p', $changed->name);
        self::assertStringContainsString('"p" ON', $changed->toString());
    }

    public function testWithTableReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $changed = $statement->withTable(new QualifiedName(['docs']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 'docs']), $statement->table);
        self::assertEquals(new QualifiedName(['docs']), $changed->table);
        self::assertStringContainsString('ON "docs" RENAME', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER POLICY "owner_only"', $changed->toString());
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $changed = $statement->withNewName('q');
        self::assertNotSame($statement, $changed);
        self::assertEquals('owners', $statement->newName);
        self::assertEquals('q', $changed->newName);
        self::assertStringContainsString('RENAME TO "q"', $changed->toString());
    }

    public function testRejectsAnEmptyNewName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY IF EXISTS owner_only ON app.docs RENAME TO owners');
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNewName('');
    }

    public function testWithTableAcceptsACatalogQualifiedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY p ON t RENAME TO q', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        self::assertSame(['a', 'b', 't'], $statement->withTable(new QualifiedName(['a', 'b', 't']))->table->parts);
    }

    public function testWithTableRejectsAFourPartTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY p ON t RENAME TO q', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTable(new QualifiedName(['x', 'a', 'b', 't']));
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER POLICY p ON t RENAME TO q', strict: false);
        self::assertInstanceOf(RenamePolicyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }
}
