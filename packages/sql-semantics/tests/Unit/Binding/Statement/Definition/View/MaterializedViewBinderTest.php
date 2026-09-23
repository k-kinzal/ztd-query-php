<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\DropMaterializedViewsStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\RefreshMaterializedViewStatement;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\View\MaterializedViewBinder::class)]
#[Medium]
final class MaterializedViewBinderTest extends TestCase
{
    public function testBindRecognizesTheMaterializedKeywordAfterModifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE UNLOGGED MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        self::assertTrue($statement->unlogged);
    }

    public function testBindIgnoresOrdinaryViews(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertNotInstanceOf(CreateMaterializedViewStatement::class, $statement);
    }

    public function testCreateReadsEveryStorageClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE MATERIALIZED VIEW IF NOT EXISTS s.m (x) USING heap WITH (fillfactor = 70, autovacuum_enabled) TABLESPACE ts AS SELECT a FROM t WITH NO DATA');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        self::assertSame(['s', 'm'], $statement->name->parts);
        self::assertSame(['x'], $statement->columns);
        self::assertTrue($statement->ifNotExists);
        self::assertSame('heap', $statement->accessMethod);
        self::assertSame('ts', $statement->tablespace);
        self::assertFalse($statement->withData);
        self::assertCount(2, $statement->storageParameters);
        self::assertSame(ImpliedSetting::Enabled, $statement->storageParameters[1]->value);
    }

    public function testRefreshReadsConcurrencyAndPopulation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $concurrent = $binder->bind('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m WITH DATA');
        $empty = $binder->bind('REFRESH MATERIALIZED VIEW m WITH NO DATA');
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $concurrent);
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $empty);
        self::assertTrue($concurrent->concurrently);
        self::assertTrue($concurrent->withData);
        self::assertFalse($empty->concurrently);
        self::assertFalse($empty->withData);
    }

    public function testRefreshDiagnosesAConcurrentRefreshWithoutData(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW CONCURRENTLY m WITH NO DATA');
    }

    public function testDropReadsSelectionExistenceAndDependencyPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW IF EXISTS a, s.b RESTRICT');
        self::assertInstanceOf(DropMaterializedViewsStatement::class, $statement);
        self::assertSame([['a'], ['s', 'b']], array_map(static fn ($name): array => $name->parts, $statement->names));
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
    }

}
