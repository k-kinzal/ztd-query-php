<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\QueryParts;

#[CoversClass(QueryParts::class)]
#[Medium]
final class QueryPartsTest extends TestCase
{
    public function testWithIsEmptyWithoutCommonTableExpressions(): void
    {
        self::assertSame('', QueryParts::with(null, Dialect::PostgreSql)->toString());
    }

    #[TestWith(['WITH c AS MATERIALIZED (SELECT 1 AS x), d(y) AS NOT MATERIALIZED (SELECT 2) SELECT x FROM c', 'WITH "c" AS MATERIALIZED(SELECT 1 AS "x"), "d"("y") AS NOT MATERIALIZED(SELECT 2)'])]
    #[TestWith(['WITH RECURSIVE c(x) AS (SELECT 1) SELECT x FROM c', 'WITH RECURSIVE "c"("x") AS (SELECT 1)'])]
    #[TestWith(['WITH w AS (INSERT INTO t (id) VALUES (1) RETURNING id) SELECT id FROM w', 'WITH "w" AS (INSERT INTO "public"."t"("id") VALUES (1) RETURNING "id" AS "id")'])]
    public function testWithSerializesNamesColumnsMaterializationAndOperands(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, QueryParts::with($statement->ctes, Dialect::PostgreSql)->toString());
        self::assertStringStartsWith($expected . ' SELECT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t LIMIT 2 OFFSET 3', 'LIMIT 2 OFFSET 3'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t OFFSET 3', 'OFFSET 3'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t ORDER BY id OFFSET 3 ROWS FETCH FIRST 2 ROWS WITH TIES', 'OFFSET 3 ROWS FETCH FIRST 2 ROWS WITH TIES'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t LIMIT 3, 2', 'LIMIT 2 OFFSET 3'])]
    #[TestWith([Dialect::Sqlite, 'SELECT id FROM t LIMIT 2', 'LIMIT 2'])]
    public function testPaginationWritesTheBoundLimitOffsetAndTiesPolicy(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, QueryParts::pagination($statement->limit, $statement->offset, $statement->withTies, $dialect)->toString());
        self::assertStringEndsWith(' ' . $expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([Dialect::MySql, 'LIMIT 18446744073709551615 OFFSET 3'])]
    #[TestWith([Dialect::Sqlite, 'LIMIT -1 OFFSET 3'])]
    #[TestWith([Dialect::PostgreSql, 'OFFSET 3'])]
    public function testPaginationSuppliesAnUnboundedLimitWhereAnOffsetRequiresOne(Dialect $dialect, string $expected): void
    {
        self::assertSame($expected, QueryParts::pagination(null, Expression::literal(3, $dialect), false, $dialect)->toString());
    }

    public function testPaginationWritesTiesWithoutARowCount(): void
    {
        self::assertSame('FETCH FIRST ROWS WITH TIES', QueryParts::pagination(null, null, true, Dialect::PostgreSql)->toString());
        self::assertSame('', QueryParts::pagination(null, null, false, Dialect::PostgreSql)->toString());
    }
}
