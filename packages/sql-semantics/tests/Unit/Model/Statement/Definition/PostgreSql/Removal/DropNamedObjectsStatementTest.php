<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropNamedObjectsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropNamedObjectsStatement::class)]
#[Medium]
final class DropNamedObjectsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        self::assertSame(Kind\NamedObjectKind::Schema, $statement->objectKind);
        self::assertSame(['app', 'archive'], $statement->names);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame('DROP SCHEMA IF EXISTS "app", "archive" CASCADE', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withObjectKind(Kind\NamedObjectKind::Extension);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\NamedObjectKind::Schema, $statement->objectKind);
        self::assertEquals(Kind\NamedObjectKind::Extension, $changed->objectKind);
        self::assertStringContainsString('DROP EXTENSION', $changed->toString());
    }

    public function testWithNamesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withNames(['x']);
        self::assertNotSame($statement, $changed);
        self::assertEquals(['app', 'archive'], $statement->names);
        self::assertEquals(['x'], $changed->names);
        self::assertStringContainsString('EXISTS "x" CASCADE', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('DROP SCHEMA "app"', $changed->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Cascade, $statement->behavior);
        self::assertEquals(DropBehavior::Restrict, $changed->behavior);
        self::assertStringContainsString('"archive" RESTRICT', $changed->toString());
    }

    public function testRejectsAServerRemovedByItsOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EXTENSION e');
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObjectKind(Kind\NamedObjectKind::Server);
    }
}
