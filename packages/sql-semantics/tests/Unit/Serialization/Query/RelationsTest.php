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

    public function testWriteSerializesRowsFromWithColumnAliases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (generate_series(1,2), generate_series(1,3)) AS r(a, b)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertNotNull($statement->from);
        self::assertSame('ROWS FROM("generate_series"(1, 2), "generate_series"(1, 3)) AS "r"("a", "b")', Relations::write($statement->from, Dialect::PostgreSql)->toString());
    }

    public function testIndexHintsWritesEachHintAfterTheAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY k (a))')))->bind('SELECT a FROM t x IGNORE KEY FOR JOIN (k) USE INDEX ()');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        self::assertSame(['IGNORE INDEX FOR JOIN(`k`)', 'USE INDEX()'], array_map(static fn ($tree): string => $tree->toString(), Relations::indexHints($statement->from, Dialect::MySql)));
        self::assertSame('`t` AS `x` IGNORE INDEX FOR JOIN(`k`) USE INDEX()', Relations::write($statement->from, Dialect::MySql)->toString());
        $derived = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT * FROM (SELECT 1) AS d');
        self::assertInstanceOf(BoundSelect::class, $derived);
        self::assertInstanceOf(DerivedRelation::class, $derived->from);
        self::assertSame([], Relations::indexHints($derived->from, Dialect::MySql));
    }


    public function testPartitionsWritesTheMySqlPartitionSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('SELECT id FROM t PARTITION (p0, `p 1`)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        self::assertSame('PARTITION (`p0`, `p 1`)', implode(' ', array_map(static fn ($tree): string => $tree->toString(), Relations::partitions($statement->from, Dialect::MySql))));
    }

    public function testIndexingWritesTheSqliteDirective(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INT); CREATE INDEX ix ON t(id)'));
        $indexed = $binder->bind('SELECT id FROM t INDEXED BY ix');
        $unindexed = $binder->bind('SELECT id FROM t NOT INDEXED');
        self::assertInstanceOf(BoundSelect::class, $indexed);
        self::assertInstanceOf(BoundSelect::class, $unindexed);
        self::assertInstanceOf(TableReference::class, $indexed->from);
        self::assertInstanceOf(TableReference::class, $unindexed->from);
        self::assertSame(['INDEXED BY', '"ix"'], array_map(static fn ($tree): string => $tree->toString(), Relations::indexing($indexed->from, Dialect::Sqlite)));
        self::assertSame(['NOT INDEXED'], array_map(static fn ($tree): string => $tree->toString(), Relations::indexing($unindexed->from, Dialect::Sqlite)));
    }

    public function testSampleWritesMethodArgumentsAndSeed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INT)')))->bind('SELECT id FROM t TABLESAMPLE s.m (1, 2) REPEATABLE (3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        self::assertSame('TABLESAMPLE "s"."m" (1, 2) REPEATABLE (3)', implode(' ', array_map(static fn ($tree): string => $tree->toString(), Relations::sample($statement->from))));
    }

    #[TestWith(['DELETE FROM t AS x PARTITION (p0) WHERE x.id = 1', 'DELETE FROM `t` AS `x` PARTITION(`p0`) WHERE (`x`.`id` = 1)'])]
    #[TestWith(['DELETE FROM t x WHERE x.id = 1', 'DELETE FROM `t` AS `x` WHERE (`x`.`id` = 1)'])]
    public function testDeletionWritesTheAliasBeforeThePartitions(string $sql, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build('CREATE TABLE t (id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $statement);
        self::assertSame($serialized, $statement->toString());
        self::assertSame($serialized, $binder->bind($serialized)->toString());
        self::assertSame(Relations::write($statement->target, Dialect::PostgreSql)->toString(), Relations::deletion($statement->target, Dialect::PostgreSql)->toString());
    }
}
