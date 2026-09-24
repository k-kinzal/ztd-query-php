<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\Extension\UpdateExtensionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdateExtensionStatement::class)]
#[Medium]
final class UpdateExtensionStatementTest extends TestCase
{
    public function testBindsTheVersionAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER EXTENSION hstore UPDATE TO v2');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        self::assertSame(['hstore', 'v2'], [$statement->name, $statement->version]);
        self::assertSame('ALTER EXTENSION "hstore" UPDATE TO \'v2\'', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER EXTENSION "hstore" UPDATE', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheExtension(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $changed = $statement->withName('citext');
        self::assertSame('hstore', $statement->name);
        self::assertSame('ALTER EXTENSION "citext" UPDATE', $changed->toString());
    }

    public function testWithVersionReplacesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $changed = $statement->withVersion('1.8');
        self::assertNull($statement->version);
        self::assertSame('ALTER EXTENSION "hstore" UPDATE TO \'1.8\'', $changed->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withVersion('');
    }
}
