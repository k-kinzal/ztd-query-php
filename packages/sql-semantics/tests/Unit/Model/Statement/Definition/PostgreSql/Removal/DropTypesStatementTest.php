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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropTypesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTypesStatement::class)]
#[Medium]
final class DropTypesStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        self::assertSame(Kind\TypeKind::Domain, $statement->typeKind);
        self::assertSame('app.percent', $statement->types[1]->name);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Default, $statement->behavior);
        self::assertSame('DROP DOMAIN IF EXISTS "app"."money", "app"."percent"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithTypeKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $changed = $statement->withTypeKind(Kind\TypeKind::Type);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\TypeKind::Domain, $statement->typeKind);
        self::assertEquals(Kind\TypeKind::Type, $changed->typeKind);
        self::assertStringContainsString('DROP TYPE IF EXISTS', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTypesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $changed = $statement->withTypes([\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text')]);
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->types, $statement->types);
        self::assertSame(['text'], array_column($changed->types, 'name'));
        self::assertStringContainsString('EXISTS text', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('DROP DOMAIN "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DOMAIN IF EXISTS app.money, app.percent', strict: false);
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Cascade);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Default, $statement->behavior);
        self::assertEquals(DropBehavior::Cascade, $changed->behavior);
        self::assertStringContainsString('"percent" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsATypeFromAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TYPE t');
        self::assertInstanceOf(DropTypesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTypes([\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer')]);
    }
}
