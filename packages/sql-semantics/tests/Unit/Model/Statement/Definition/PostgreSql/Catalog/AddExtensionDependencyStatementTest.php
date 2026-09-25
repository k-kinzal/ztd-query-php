<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\AddExtensionDependencyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddExtensionDependencyStatement::class)]
#[Medium]
final class AddExtensionDependencyStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis', strict: false);
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Index, new QualifiedName(['app', 'ix'])), $statement->object);
        self::assertSame('postgis', $statement->extension);
        self::assertSame('ALTER INDEX "app"."ix" DEPENDS ON EXTENSION "postgis"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis', strict: false);
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis', strict: false);
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis', strict: false);
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t'])));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Index, new QualifiedName(['app', 'ix'])), $statement->object);
        self::assertEquals(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t'])), $changed->object);
        self::assertStringContainsString('ALTER TRIGGER "tr" ON "t" DEPENDS ON EXTENSION', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithExtensionReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis', strict: false);
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        $changed = $statement->withExtension('hstore');
        self::assertNotSame($statement, $changed);
        self::assertEquals('postgis', $statement->extension);
        self::assertEquals('hstore', $changed->extension);
        self::assertStringContainsString('DEPENDS ON EXTENSION "hstore"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnObjectThatCannotDependOnAnExtension(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX app.ix DEPENDS ON EXTENSION postgis');
        self::assertInstanceOf(AddExtensionDependencyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t'])));
    }
}
