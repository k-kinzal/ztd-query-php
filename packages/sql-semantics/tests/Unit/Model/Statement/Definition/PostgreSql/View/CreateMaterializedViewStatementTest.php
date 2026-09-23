<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateMaterializedViewStatement::class)]
#[Medium]
final class CreateMaterializedViewStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryStorageOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS s.m (x) USING heap WITH (fillfactor = 70) TABLESPACE ts AS SELECT a FROM t WITH NO DATA');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertTrue($copy->unlogged);
        self::assertTrue($copy->ifNotExists);
        self::assertSame('heap', $copy->accessMethod);
        self::assertSame('ts', $copy->tablespace);
        self::assertFalse($copy->withData);
        self::assertCount(1, $copy->storageParameters);
    }

    public function testWithOriginRejectsAnotherLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithQueryReplacesTheDefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)'));
        $statement = $binder->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $query = $binder->bind('SELECT a FROM t');
        self::assertInstanceOf(BoundQuery::class, $query);
        $changed = $statement->withQuery($query);
        self::assertSame($query->toString(), $changed->query->toString());
        self::assertSame('CREATE MATERIALIZED VIEW "m" AS SELECT "a" AS "a" FROM "public"."t"', $changed->toString());
    }

    public function testWithPopulationTogglesTheInitialLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $changed = $statement->withPopulation(false);
        self::assertTrue($statement->withData);
        self::assertFalse($changed->withData);
        self::assertSame('CREATE MATERIALIZED VIEW "m" AS SELECT 1 WITH NO DATA', $changed->toString());
    }

    public function testRejectsEmptyStorageNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateMaterializedViewStatement($statement->origin, $statement->name, $statement->query, accessMethod: '');
    }

}
