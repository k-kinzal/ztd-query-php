<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\AllRows;
use SqlSemantics\Model\Query\DistinctOn;
use SqlSemantics\Model\Query\DistinctRows;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Statement\TableStatement;
use SqlSemantics\Model\Statement\ValuesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\Queries;

#[CoversClass(Queries::class)]
#[Medium]
final class QueriesTest extends TestCase
{
    public function testWriteSerializesEverySelectStageInEvaluationOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('WITH c AS (SELECT 1 AS x) SELECT DISTINCT id FROM t WHERE id > 0 GROUP BY id HAVING COUNT(*) > 1 WINDOW w AS (PARTITION BY id) ORDER BY id LIMIT 1 OFFSET 2 FOR UPDATE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expected = 'WITH "c" AS (SELECT 1 AS "x") SELECT DISTINCT "id" AS "id" FROM "public"."t" WHERE ("id" > 0) GROUP BY "id" HAVING ("count"(*) > 1) WINDOW "w" AS (PARTITION BY "id") ORDER BY "id" ASC LIMIT 1 OFFSET 2 FOR UPDATE';
        self::assertSame($expected, Queries::write($statement)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith([Dialect::MySql, 'VALUES ROW(1,2), ROW(3,4)', 'VALUES ROW(1, 2), ROW(3, 4)', ValuesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'VALUES (1,2), (3,4)', 'VALUES (1, 2), (3, 4)', ValuesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'TABLE t', 'TABLE "public"."t"', TableStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 1', 'SELECT 1 UNION SELECT 2 ORDER BY 1 ASC LIMIT 1', CompoundStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 UNION (SELECT 2 UNION SELECT 3)', 'SELECT 1 UNION (SELECT 2 UNION SELECT 3)', CompoundStatement::class])]
    public function testWriteSerializesRowConstructorsTableQueriesAndSetOperations(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundQuery::class, $statement);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, Queries::write($statement)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testOperandParenthesizesAPaginatedOrCompoundOperandOutsideSqlite(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('(SELECT 1 LIMIT 1) INTERSECT ALL (SELECT 2 ORDER BY 1)');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        self::assertSame('(SELECT 1 LIMIT 1)', Queries::operand($statement->left)->toString());
        self::assertSame('(SELECT 2 ORDER BY 1 ASC)', Queries::operand($statement->right)->toString());
        $nested = $binder->bind('SELECT 1 UNION (SELECT 2 UNION SELECT 3)');
        self::assertInstanceOf(CompoundStatement::class, $nested);
        self::assertSame('SELECT 1', Queries::operand($nested->left)->toString());
        self::assertSame('(SELECT 2 UNION SELECT 3)', Queries::operand($nested->right)->toString());
    }

    #[TestWith([Dialect::MySql, 'mysql-5.6.51', 'SELECT EXISTS (SELECT 1 UNION SELECT 2 UNION ALL SELECT 3)', 'SELECT EXISTS(SELECT 1 UNION SELECT 2 UNION ALL SELECT 3)'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'SELECT 1 UNION SELECT 2 UNION SELECT 3', 'SELECT 1 UNION SELECT 2 UNION SELECT 3'])]
    #[TestWith([Dialect::PostgreSql, null, '(SELECT 1 UNION SELECT 2) INTERSECT SELECT 3', '(SELECT 1 UNION SELECT 2) INTERSECT SELECT 3'])]
    #[TestWith([Dialect::PostgreSql, null, 'SELECT 1 INTERSECT SELECT 2 EXCEPT SELECT 3', 'SELECT 1 INTERSECT SELECT 2 EXCEPT SELECT 3'])]
    public function testOperandChainsALeftSetOperationOfNoLowerPrecedence(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['mysql-5.7.44', '( SELECT 1 FOR UPDATE ) UNION SELECT 2', '(SELECT 1 FOR UPDATE) UNION SELECT 2'])]
    #[TestWith(['mysql-8.3.0', '( ( ( SELECT 1 LOCK IN SHARE MODE ) ) ) EXCEPT SELECT 2', '(SELECT 1 LOCK IN SHARE MODE) EXCEPT SELECT 2'])]
    public function testOperandParenthesizesALockedSelect(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testIntersectionRecognizesBothIntersectForms(): void
    {
        self::assertSame([false, false, true, true, false, false], array_map(Queries::intersection(...), \SqlSemantics\Model\Query\SetOperator::cases()));
    }

    public function testOperandLeavesASqliteOperandBare(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $paginated = $binder->bind('SELECT 1 LIMIT 1');
        self::assertInstanceOf(BoundSelect::class, $paginated);
        self::assertSame('SELECT 1 LIMIT 1', Queries::operand($paginated)->toString());
        $compound = $binder->bind('SELECT 1 UNION SELECT 2 LIMIT 1');
        self::assertInstanceOf(CompoundStatement::class, $compound);
        self::assertSame('SELECT 1', Queries::operand($compound->left)->toString());
        self::assertSame('SELECT 1 UNION SELECT 2 LIMIT 1', $compound->toString());
    }

    public function testQuantifierSpellsEachDuplicateEliminationForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $all = $binder->bind('SELECT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $all);
        self::assertInstanceOf(AllRows::class, $all->quantifier);
        self::assertSame('', Queries::quantifier($all->quantifier)->toString());
        $distinct = $binder->bind('SELECT DISTINCT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $distinct);
        self::assertInstanceOf(DistinctRows::class, $distinct->quantifier);
        self::assertSame('DISTINCT', Queries::quantifier($distinct->quantifier)->toString());
        $on = $binder->bind('SELECT DISTINCT ON (id) id FROM t');
        self::assertInstanceOf(BoundSelect::class, $on);
        self::assertInstanceOf(DistinctOn::class, $on->quantifier);
        self::assertSame('DISTINCT ON("id")', Queries::quantifier($on->quantifier)->toString());
        self::assertSame('SELECT DISTINCT ON("id") "id" AS "id" FROM "public"."t"', $on->toString());
    }

    public function testWindowsWritesNamedDefinitionsOnlyWhenDeclared(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $named = $binder->bind('SELECT SUM(n) OVER w FROM t WINDOW w AS (PARTITION BY id)');
        self::assertInstanceOf(BoundSelect::class, $named);
        self::assertSame('WINDOW "w" AS (PARTITION BY "id")', Queries::windows($named)->toString());
        self::assertSame('SELECT "sum"("n") OVER "w" FROM "public"."t" WINDOW "w" AS (PARTITION BY "id")', $named->toString());
        $plain = $binder->bind('SELECT n FROM t');
        self::assertInstanceOf(BoundSelect::class, $plain);
        self::assertSame('', Queries::windows($plain)->toString());
    }

    #[TestWith([Dialect::MySql, 'mysql-5.7.44', 'SELECT 1 FROM DUAL WHERE 1', 'SELECT 1 FROM DUAL WHERE 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'SELECT 1 WHERE 1', 'SELECT 1 FROM DUAL WHERE 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'SELECT 1 FROM DUAL', 'SELECT 1'])]
    #[TestWith([Dialect::PostgreSql, null, 'SELECT 1 WHERE true', 'SELECT 1 WHERE true'])]
    public function testFromNamesDualForAFilteredMySqlQueryWithoutTables(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testFromNamesDualBeforeALimitOnlyWhenAnIntoClauseFollows(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1 LIMIT 2');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame([], Queries::from($query));
        self::assertSame('FROM DUAL', Queries::from($query, true)[0]->toString());
        self::assertSame('SELECT 1 LIMIT 2', Queries::write($query)->toString());
        self::assertSame('SELECT 1 FROM DUAL LIMIT 2', Queries::write($query, true)->toString());
    }

    public function testWriteKeepsTheQueryBlockOptionsAfterTheQuantifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)'));
        $statement = $binder->bind('SELECT /*+ MAX_EXECUTION_TIME(5) */ SQL_BIG_RESULT DISTINCT STRAIGHT_JOIN a FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(5) */ DISTINCT SQL_BIG_RESULT STRAIGHT_JOIN `a` AS `a` FROM `t`', Queries::write($statement)->toString());
    }
}
