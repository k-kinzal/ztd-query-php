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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameRelationStatement::class)]
#[Medium]
final class RenameRelationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        self::assertSame(Kind\RelationKind::Sequence, $statement->relationKind);
        self::assertSame(['app', 's'], $statement->name->parts);
        self::assertSame('s_old', $statement->newName);
        self::assertTrue($statement->ifExists);
        self::assertSame('ALTER SEQUENCE IF EXISTS "app"."s" RENAME TO "s_old"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['s']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 's']), $statement->name);
        self::assertEquals(new QualifiedName(['s']), $changed->name);
        self::assertStringContainsString('EXISTS "s" RENAME', $changed->toString());
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $changed = $statement->withNewName('s2');
        self::assertNotSame($statement, $changed);
        self::assertEquals('s_old', $statement->newName);
        self::assertEquals('s2', $changed->newName);
        self::assertStringContainsString('TO "s2"', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS app.s RENAME TO s_old', strict: false);
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER SEQUENCE "app"', $changed->toString());
    }

    public function testWithOnlyRejectsASequence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s RENAME TO t');
        self::assertInstanceOf(RenameRelationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOnly(true);
    }
}
