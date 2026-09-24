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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SetObjectSchemaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetObjectSchemaStatement::class)]
#[Medium]
final class SetObjectSchemaStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats', strict: false);
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        self::assertInstanceOf(Catalog\AggregateIdentity::class, $statement->object);
        self::assertSame('stats', $statement->schema);
        self::assertSame('ALTER AGGREGATE "app"."median"(numeric) SET SCHEMA "stats"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats', strict: false);
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats', strict: false);
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats', strict: false);
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'postgis'));
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->object, $statement->object);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'postgis'), $changed->object);
        self::assertStringContainsString('ALTER EXTENSION "postgis" SET SCHEMA', $changed->toString());
    }

    public function testWithSchemaReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats', strict: false);
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        $changed = $statement->withSchema('archive');
        self::assertNotSame($statement, $changed);
        self::assertEquals('stats', $statement->schema);
        self::assertEquals('archive', $changed->schema);
        self::assertStringContainsString('SET SCHEMA "archive"', $changed->toString());
    }

    public function testRejectsAnObjectOutsideSchemas(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.median(numeric) SET SCHEMA stats');
        self::assertInstanceOf(SetObjectSchemaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, 'app'));
    }
}
