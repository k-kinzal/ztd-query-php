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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\SetRelationSchemaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetRelationSchemaStatement::class)]
#[Medium]
final class SetRelationSchemaStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        self::assertSame(Kind\RelationKind::Table, $statement->relationKind);
        self::assertSame(['app', 't'], $statement->name->parts);
        self::assertSame('archive', $statement->schema);
        self::assertTrue($statement->ifExists);
        self::assertFalse($statement->only);
        self::assertSame('ALTER TABLE IF EXISTS "app"."t" SET SCHEMA "archive"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithRelationKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $changed = $statement->withRelationKind(Kind\RelationKind::Sequence);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\RelationKind::Table, $statement->relationKind);
        self::assertEquals(Kind\RelationKind::Sequence, $changed->relationKind);
        self::assertStringContainsString('ALTER SEQUENCE IF EXISTS', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['u']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 't']), $statement->name);
        self::assertEquals(new QualifiedName(['u']), $changed->name);
        self::assertStringContainsString('EXISTS "u" SET', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithSchemaReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $changed = $statement->withSchema('old');
        self::assertNotSame($statement, $changed);
        self::assertEquals('archive', $statement->schema);
        self::assertEquals('old', $changed->schema);
        self::assertStringContainsString('SET SCHEMA "old"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('ALTER TABLE "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOnlyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE IF EXISTS app.t SET SCHEMA archive', strict: false);
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $changed = $statement->withOnly(true);
        self::assertNotSame($statement, $changed);
        self::assertEquals(false, $statement->only);
        self::assertEquals(true, $changed->only);
        self::assertStringContainsString('ONLY "app"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE t SET SCHEMA s');
        self::assertInstanceOf(SetRelationSchemaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRelationKind(Kind\RelationKind::Index);
    }
}
