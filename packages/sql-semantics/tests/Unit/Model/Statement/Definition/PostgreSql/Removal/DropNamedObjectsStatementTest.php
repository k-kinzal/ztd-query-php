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
        self::assertSame('DROP SCHEMA IF EXISTS "app", "archive" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
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
        self::assertStringContainsString('DROP EXTENSION', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNamesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withNames(['x']);
        self::assertNotSame($statement, $changed);
        self::assertEquals(['app', 'archive'], $statement->names);
        self::assertEquals(['x'], $changed->names);
        self::assertStringContainsString('EXISTS "x" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('DROP SCHEMA "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS app, archive CASCADE', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Cascade, $statement->behavior);
        self::assertEquals(DropBehavior::Restrict, $changed->behavior);
        self::assertStringContainsString('"archive" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAServerRemovedByItsOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EXTENSION e');
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObjectKind(Kind\NamedObjectKind::Server);
    }

    public function testIfExistsDefaultsToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SCHEMA IF EXISTS s', strict: false);
        self::assertInstanceOf(DropNamedObjectsStatement::class, $statement);
        self::assertFalse((new DropNamedObjectsStatement($statement->origin, $statement->objectKind, $statement->names))->ifExists);
    }
}
