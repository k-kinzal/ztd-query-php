<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableOccurrence::class)]
#[Medium]
final class TableOccurrenceTest extends TestCase
{
    #[TestWith(['UPDATE ONLY t SET id=2', 'UPDATE ONLY "public"."t" SET "id" = 2'])]
    #[TestWith(['DELETE FROM ONLY(t) WHERE id=1', 'DELETE FROM ONLY "public"."t" WHERE ("id" = 1)'])]
    public function testBindRetainsMutationRowScope(string $sql, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertTrue($statement instanceof UpdateStatement || $statement instanceof DeleteStatement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->affectedTables()[0]);
        self::assertSame($serialized, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($serialized);
        self::assertTrue($rebound instanceof UpdateStatement || $rebound instanceof DeleteStatement);
        self::assertInstanceOf(OnlyTableReference::class, $rebound->affectedTables()[0]);
    }
    public function testResolvePreservesQuotedNamespaceAndAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE `select`.`from`(id INTEGER)');
        $statement = (new Binder($schema))->bind('LOCK TABLES `select`.`from` AS `where` READ');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Locking\LockTablesStatement::class, $statement);
        self::assertSame(['select', 'from'], $statement->locks[0]->table->name->parts);
        self::assertSame('where', $statement->locks[0]->table->alias);
        self::assertSame($schema->tables[0], $statement->locks[0]->table->declaration);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testHintsReadEveryIndexHintOfATable(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (a INT, KEY k (a))'));
        $statement = $binder->bind('SELECT a FROM t AS x USE INDEX FOR JOIN (k, PRIMARY) USE KEY () FORCE KEY FOR GROUP BY (k) IGNORE INDEX FOR ORDER BY (`k`)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $statement->from);
        $hints = $statement->from->indexHints;
        self::assertSame([\SqlSemantics\Model\Query\Optimization\IndexHintAction::Use, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Use, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Force, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Ignore], array_column($hints, 'action'));
        self::assertSame([\SqlSemantics\Model\Query\Optimization\IndexHintScope::Join, null, \SqlSemantics\Model\Query\Optimization\IndexHintScope::GroupBy, \SqlSemantics\Model\Query\Optimization\IndexHintScope::OrderBy], array_column($hints, 'scope'));
        self::assertSame([['k', 'PRIMARY'], [], ['k'], ['k']], array_column($hints, 'indexes'));
        $expected = 'SELECT `a` AS `a` FROM `t` AS `x` USE INDEX FOR JOIN(`k`, `PRIMARY`) USE INDEX() FORCE INDEX FOR GROUP BY(`k`) IGNORE INDEX FOR ORDER BY(`k`)';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testHintsLeaveOtherDialectsWithoutHints(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 FROM t');
        self::assertSame([], TableOccurrence::hints($tree, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)));
    }

    public function testBindKeepsTheHintsOfAnUpdatedTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY k (a))'));
        $statement = $binder->bind('UPDATE t USE INDEX (k) SET a = 1');
        self::assertInstanceOf(UpdateStatement::class, $statement);
        self::assertSame('UPDATE `t` USE INDEX(`k`) SET `a` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }


    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testPartitionsKeepTheSelectedPartitionsOfEveryTarget(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT) PARTITION BY HASH(id) PARTITIONS 2'));
        $cases = [
            'SELECT * FROM t PARTITION (p0, p1) x' => 'SELECT `x`.`id` AS `id`, `x`.`a` AS `a` FROM `t` PARTITION(`p0`, `p1`) AS `x`',
            'INSERT INTO t PARTITION (p0) VALUES (1, 2)' => 'INSERT INTO `t` PARTITION(`p0`) VALUES (1, 2)',
            'REPLACE t PARTITION (p0) SET a = 1' => 'REPLACE INTO `t` PARTITION(`p0`) SET `a` = 1',
            'UPDATE t PARTITION (p0) SET a = 1' => 'UPDATE `t` PARTITION(`p0`) SET `a` = 1',
            'DELETE FROM t PARTITION (p0) WHERE id = 1' => 'DELETE FROM `t` PARTITION(`p0`) WHERE (`id` = 1)',
            'INSERT t PARTITION (p0) SELECT * FROM t PARTITION (p1)' => 'INSERT INTO `t` PARTITION(`p0`) SELECT `t`.`id` AS `id`, `t`.`a` AS `a` FROM `t` PARTITION(`p1`)',
        ];
        self::assertSame(array_values($cases), array_map(static fn (string $sql): string => (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)), array_keys($cases)));
        self::assertSame(array_values($cases), array_map(static fn (string $sql): string => (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)), array_values($cases)));
        $statement = $binder->bind('SELECT * FROM t PARTITION (p0, p1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $statement->from);
        self::assertSame(['p0', 'p1'], $statement->from->partitions?->names);
        self::assertNull(TableOccurrence::partitions(null, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }

    #[TestWith(['UPDATE t INDEXED BY ix SET a = 1', 'UPDATE "main"."t" INDEXED BY "ix" SET "a" = 1'])]
    #[TestWith(['UPDATE OR REPLACE t AS x NOT INDEXED SET a = 1', 'UPDATE OR REPLACE "main"."t" AS "x" NOT INDEXED SET "a" = 1'])]
    #[TestWith(['DELETE FROM main.t AS x INDEXED BY ix RETURNING *', 'DELETE FROM "main"."t" AS "x" INDEXED BY "ix" RETURNING "x"."id" AS "id", "x"."a" AS "a"'])]
    #[TestWith(['SELECT * FROM t INDEXED BY "ix" JOIN t u NOT INDEXED', 'SELECT "t"."id" AS "id", "t"."a" AS "a", "u"."id" AS "id", "u"."a" AS "a" FROM "main"."t" INDEXED BY "ix" CROSS JOIN "main"."t" AS "u" NOT INDEXED'])]
    #[TestWith(['UPDATE t SET a = 1 FROM t u INDEXED BY ix WHERE u.id = t.id', 'UPDATE "main"."t" SET "a" = 1 FROM "main"."t" AS "u" INDEXED BY "ix" WHERE ("u"."id" = "t"."id")'])]
    public function testIndexingKeepsTheSqliteIndexDirective(string $sql, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, a INT); CREATE INDEX ix ON t(a)'));
        self::assertSame($serialized, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($serialized, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($serialized)));
        self::assertNull(TableOccurrence::indexing(null, new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite)));
    }

    public function testIndexingOfATriggerMutationIsInvalidSql(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, a INT); CREATE INDEX ix ON t(a)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind('CREATE TRIGGER tr AFTER INSERT ON t BEGIN UPDATE t INDEXED BY ix SET a = 1; END');
    }

    #[TestWith(['postgresql', 'WITH c AS (SELECT 1) SELECT * FROM c TABLESAMPLE SYSTEM (1)'])]
    #[TestWith(['sqlite', 'WITH c AS (SELECT 1) SELECT * FROM c INDEXED BY ix'])]
    #[TestWith(['mysql', 'WITH c AS (SELECT 1) SELECT * FROM c PARTITION (p0)'])]
    public function testSampleOrOtherClauseOnACommonTableExpressionIsInvalidSql(string $dialect, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::from($dialect)))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::StoredTableClause->message());
        $binder->bind($sql);
    }

    public function testSampleArgumentsSeeTheOuterQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INT, a INT)'));
        $expected = 'SELECT "t"."id" AS "id", "t"."a" AS "a", "z"."id" AS "id", "z"."a" AS "a" FROM "public"."t" CROSS JOIN LATERAL(SELECT "u"."id" AS "id", "u"."a" AS "a" FROM "public"."t" AS "u" TABLESAMPLE SYSTEM("t"."a")) AS "z"';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('SELECT * FROM t, LATERAL (SELECT * FROM t u TABLESAMPLE SYSTEM (t.a)) z')));
        self::assertNull(TableOccurrence::sample(null, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql))));
    }
}
