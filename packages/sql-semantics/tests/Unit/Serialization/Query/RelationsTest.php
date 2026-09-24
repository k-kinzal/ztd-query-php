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
use SqlSemantics\Model\Relation\AliasedRelation;
use SqlSemantics\Model\Relation\CteReference;
use SqlSemantics\Model\Relation\DerivedRelation;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\Relation\FunctionRelation;
use SqlSemantics\Model\Relation\Joining\CrossJoin;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\Relations;

#[CoversClass(Relations::class)]
#[Medium]
final class RelationsTest extends TestCase
{
    /**
     * @param non-empty-string $expected
     */
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t', '"public"."t"', TableReference::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM ONLY t AS o', 'ONLY "public"."t" AS "o"', OnlyTableReference::class])]
    #[TestWith([Dialect::PostgreSql, 'WITH c AS (SELECT 1 AS x) SELECT x FROM c', '"c"', CteReference::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 FROM t AS a, LATERAL (SELECT a.id) AS l', '"public"."t" AS "a" CROSS JOIN LATERAL(SELECT "a"."id" AS "id") AS "l"', CrossJoin::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT x FROM (t JOIN s ON t.id = s.id) AS j(x)', '("public"."t" INNER JOIN "public"."s" ON ("t"."id" = "s"."id")) AS "j"("x")', AliasedRelation::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT * FROM generate_series(1,3) AS g(v)', '"generate_series"(1, 3) AS "g"("v")', FunctionRelation::class])]
    #[TestWith([Dialect::MySql, 'SELECT * FROM (SELECT 1 AS a) AS d', '(SELECT 1 AS `a`) AS `d`', DerivedRelation::class])]
    #[TestWith([Dialect::Sqlite, 'SELECT id FROM t AS a', '"main"."t" AS "a"', TableReference::class])]
    public function testWriteSerializesEachRelationFormWithItsAliases(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $from = $statement->from;
        self::assertNotNull($from);
        self::assertSame($class, $from::class);
        self::assertSame($expected, Relations::write($from, $dialect)->toString());
        self::assertStringEndsWith($expected, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWriteSerializesADocumentRelation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(data JSONB)'));
        $statement = $binder->bind("SELECT j.* FROM t, JSON_TABLE (t.data FORMAT JSON, '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(DocumentRelation::class, $relation);
        self::assertSame('JSON_TABLE("t"."data" FORMAT JSON, \'$[*]\' COLUMNS("n" FOR ORDINALITY)) AS "j"', Relations::write($relation, Dialect::PostgreSql)->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testTargetWritesTheRelationNameWithoutItsAlias(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $only = $binder->bind('SELECT id FROM ONLY t AS o');
        self::assertInstanceOf(BoundSelect::class, $only);
        self::assertInstanceOf(OnlyTableReference::class, $only->from);
        self::assertSame('ONLY "public"."t"', Relations::target($only->from, Dialect::PostgreSql)->toString());
        $cte = $binder->bind('WITH c AS (SELECT 1 AS x) SELECT x FROM c AS d');
        self::assertInstanceOf(BoundSelect::class, $cte);
        self::assertInstanceOf(CteReference::class, $cte->from);
        self::assertSame('"c"', Relations::target($cte->from, Dialect::PostgreSql)->toString());
        self::assertSame('"c" AS "d"', Relations::write($cte->from, Dialect::PostgreSql)->toString());
    }
}
