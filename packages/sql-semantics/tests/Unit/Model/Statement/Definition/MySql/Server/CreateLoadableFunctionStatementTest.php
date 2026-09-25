<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Server\LoadableResult;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateLoadableFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateLoadableFunctionStatement::class)]
#[Medium]
final class CreateLoadableFunctionStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRegistration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("CREATE FUNCTION `f` RETURNS STRING SONAME 'u.so'", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame(['f', 'g'], [$statement->name, $statement->withName('g')->name]);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithReturnsReplacesTheResultKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame("CREATE FUNCTION `f` RETURNS DECIMAL SONAME 'u.so'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withReturns(LoadableResult::Decimal)));
        self::assertSame(LoadableResult::String, $statement->returns);
    }

    public function testWithLibraryReplacesTheSharedLibrary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame("CREATE FUNCTION `f` RETURNS STRING SONAME 'it''s.so'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLibrary("it's.so")));
    }

    public function testWithAggregateDeclaresAnAggregateFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS REAL SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame("CREATE AGGREGATE FUNCTION `f` RETURNS REAL SONAME 'u.so'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAggregate(true)));
        self::assertFalse($statement->aggregate);
    }

    public function testWithIfNotExistsRequiresMySqlEight(): void
    {
        $modern = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS INTEGER SONAME 'u.so'");
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CREATE FUNCTION f RETURNS INTEGER SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $modern);
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $legacy);
        self::assertSame("CREATE FUNCTION IF NOT EXISTS `f` RETURNS INTEGER SONAME 'u.so'", (new \SqlSemantics\SimpleSerializer())->serialize($modern->withIfNotExists(true)));
        $this->expectException(InvalidStructure::class);
        $legacy->withIfNotExists(true);
    }
}
