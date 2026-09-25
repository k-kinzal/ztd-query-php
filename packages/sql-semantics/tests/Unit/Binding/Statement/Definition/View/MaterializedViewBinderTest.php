<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindReadsEachLowercaseForm(): array
    {
        return [
            [Dialect::PostgreSql, null, 'create materialized view mv2 as select 1 as a with no data', [CreateMaterializedViewStatement::class, 'CREATE MATERIALIZED VIEW "mv2" AS SELECT 1 AS "a" WITH NO DATA']],
            [Dialect::PostgreSql, null, 'refresh materialized view mv with no data', [RefreshMaterializedViewStatement::class, 'REFRESH MATERIALIZED VIEW "mv" WITH NO DATA']],
            [Dialect::PostgreSql, null, 'refresh materialized view concurrently mv', [RefreshMaterializedViewStatement::class, 'REFRESH MATERIALIZED VIEW CONCURRENTLY "mv"']],
            [Dialect::PostgreSql, null, 'drop materialized view if exists mv, mv2 cascade', [DropMaterializedViewsStatement::class, 'DROP MATERIALIZED VIEW IF EXISTS "mv", "mv2" CASCADE']],
        ];
    }

    #[DataProvider('providerBindReadsEachLowercaseForm')]
    public function testBindReadsEachLowercaseForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE MATERIALIZED VIEW mv AS SELECT 1 AS a')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }

    public function testRefreshRejectsAConcurrentRefreshWithoutData(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE MATERIALIZED VIEW mv AS SELECT 1 AS a'));
        $this->expectException(InvalidSql::class);
        $binder->bind('refresh materialized view concurrently mv with no data', strict: false);
    }


    public function testCreateReadsItsModifiersOutsideTheQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $plain = $binder->bind("CREATE MATERIALIZED VIEW m AS SELECT 1 AS if, 2 AS unlogged, 'WITH NO DATA' AS x");
        $modified = $binder->bind('CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS m AS SELECT 1 WITH NO DATA');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $plain);
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $modified);
        self::assertSame([false, false, true], [$plain->unlogged, $plain->ifNotExists, $plain->withData]);
        self::assertSame([true, true, false], [$modified->unlogged, $modified->ifNotExists, $modified->withData]);
    }

    /**
     * @param list<string> $words
     */
    #[\PHPUnit\Framework\Attributes\TestWith([['CREATE', 'MATERIALIZED', 'VIEW', 'M'], true])]
    #[\PHPUnit\Framework\Attributes\TestWith([['CREATE', 'UNLOGGED', 'MATERIALIZED', 'VIEW', 'M'], true])]
    #[\PHPUnit\Framework\Attributes\TestWith([['CREATE', 'VIEW', 'MATERIALIZED', 'AS'], false])]
    #[\PHPUnit\Framework\Attributes\TestWith([['DROP', 'VIEW', 'MATERIALIZED'], false])]
    public function testDeclaresRecognizesTheObjectClassNotAnObjectName(array $words, bool $expected): void
    {
        self::assertSame($expected, \SqlSemantics\Binding\Statement\Definition\View\MaterializedViewBinder::declares($words));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE RECURSIVE VIEW materialized (a) AS TABLE x', 'CREATE RECURSIVE VIEW "materialized"("a") AS TABLE "public"."x"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE VIEW materialized AS TABLE x', 'CREATE VIEW "materialized" AS TABLE "public"."x"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP VIEW materialized', 'DROP VIEW "materialized"'])]
    public function testBindLeavesAViewNamedMaterializedToOrdinaryViews(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE x (a int)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindKeepsTheLockingClauseOfTheMaterializedViewQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE MATERIALIZED VIEW m AS SELECT a FROM t FOR UPDATE OF t NOWAIT WITH NO DATA');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        $expected = 'CREATE MATERIALIZED VIEW "m" AS SELECT "a" AS "a" FROM "public"."t" FOR UPDATE OF "t" NOWAIT WITH NO DATA';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
