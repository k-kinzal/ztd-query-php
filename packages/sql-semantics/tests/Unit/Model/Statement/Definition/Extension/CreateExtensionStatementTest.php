<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\Extension\CreateExtensionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateExtensionStatement::class)]
#[Medium]
final class CreateExtensionStatementTest extends TestCase
{
    public function testBindsTheOptionsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE EXTENSION IF NOT EXISTS hstore WITH SCHEMA app VERSION '1.8' CASCADE");
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        self::assertSame(['hstore', true, 'app', '1.8', true], [$statement->name, $statement->ifNotExists, $statement->schema, $statement->version, $statement->cascade]);
        self::assertSame('CREATE EXTENSION IF NOT EXISTS "hstore" SCHEMA "app" VERSION \'1.8\' CASCADE', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheExtension(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $changed = $statement->withName('citext');
        self::assertSame('hstore', $statement->name);
        self::assertSame('CREATE EXTENSION "citext"', $changed->toString());
    }

    public function testWithIfNotExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE EXTENSION IF NOT EXISTS "hstore"', $changed->toString());
    }

    public function testWithSchemaReplacesTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore SCHEMA app');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $changed = $statement->withSchema(null);
        self::assertSame('app', $statement->schema);
        self::assertSame('CREATE EXTENSION "hstore"', $changed->toString());
    }

    public function testWithVersionReplacesTheVersion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $changed = $statement->withVersion("1'0");
        self::assertNull($statement->version);
        self::assertSame('CREATE EXTENSION "hstore" VERSION \'1\'\'0\'', $changed->toString());
    }

    public function testWithVersionRejectsAnEmptyVersion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withVersion('');
    }

    public function testWithCascadeReplacesThePrerequisitePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore CASCADE');
        self::assertInstanceOf(CreateExtensionStatement::class, $statement);
        $changed = $statement->withCascade(false);
        self::assertTrue($statement->cascade);
        self::assertSame('CREATE EXTENSION "hstore"', $changed->toString());
    }
}
