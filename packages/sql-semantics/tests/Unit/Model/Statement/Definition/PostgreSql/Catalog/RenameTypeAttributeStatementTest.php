<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameTypeAttributeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameTypeAttributeStatement::class)]
#[Medium]
final class RenameTypeAttributeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        self::assertSame(['app', 'point'], $statement->type->parts);
        self::assertSame('x', $statement->attribute);
        self::assertSame('px', $statement->newName);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame('ALTER TYPE "app"."point" RENAME ATTRIBUTE "x" TO "px" CASCADE', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $changed = $statement->withType(new QualifiedName(['pt']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 'point']), $statement->type);
        self::assertEquals(new QualifiedName(['pt']), $changed->type);
        self::assertStringContainsString('ALTER TYPE "pt"', $changed->toString());
    }

    public function testWithAttributeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $changed = $statement->withAttribute('y');
        self::assertNotSame($statement, $changed);
        self::assertEquals('x', $statement->attribute);
        self::assertEquals('y', $changed->attribute);
        self::assertStringContainsString('ATTRIBUTE "y"', $changed->toString());
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $changed = $statement->withNewName('py');
        self::assertNotSame($statement, $changed);
        self::assertEquals('px', $statement->newName);
        self::assertEquals('py', $changed->newName);
        self::assertStringContainsString('TO "py"', $changed->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE', strict: false);
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Cascade, $statement->behavior);
        self::assertEquals(DropBehavior::Restrict, $changed->behavior);
        self::assertStringContainsString('"px" RESTRICT', $changed->toString());
    }

    public function testRejectsAnEmptyAttribute(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.point RENAME ATTRIBUTE x TO px CASCADE');
        self::assertInstanceOf(RenameTypeAttributeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAttribute('');
    }
}
