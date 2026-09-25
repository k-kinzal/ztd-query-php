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
        self::assertSame('ALTER EXTENSION "hstore" UPDATE TO \'v2\'', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER EXTENSION "hstore" UPDATE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
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
        self::assertSame('ALTER EXTENSION "citext" UPDATE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithVersionReplacesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
        self::assertInstanceOf(UpdateExtensionStatement::class, $statement);
        $changed = $statement->withVersion('1.8');
        self::assertNull($statement->version);
        self::assertSame('ALTER EXTENSION "hstore" UPDATE TO \'1.8\'', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        $this->expectException(InvalidStructure::class);
        $statement->withVersion('');
    }
}
