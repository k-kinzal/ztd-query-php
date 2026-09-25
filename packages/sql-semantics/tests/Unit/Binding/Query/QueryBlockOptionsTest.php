<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryBlockOptions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\SelectOption;
use SqlSemantics\Model\Statement\Insert\InsertSelectStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QueryBlockOptions::class)]
#[Medium]
final class QueryBlockOptionsTest extends TestCase
{
    /**
     * @param list<SelectOption> $options
     */
    #[TestWith(['mysql-5.6.51', 'SELECT DISTINCT SQL_CACHE HIGH_PRIORITY SQL_CALC_FOUND_ROWS a FROM t', [SelectOption::Cache, SelectOption::HighPriority, SelectOption::CalcFoundRows], 'SELECT DISTINCT SQL_CACHE HIGH_PRIORITY SQL_CALC_FOUND_ROWS `a` AS `a` FROM `t`'])]
    #[TestWith(['mysql-5.7.44', 'SELECT SQL_NO_CACHE STRAIGHT_JOIN SQL_SMALL_RESULT a FROM t', [SelectOption::NoCache, SelectOption::StraightJoin, SelectOption::SmallResult], 'SELECT SQL_NO_CACHE STRAIGHT_JOIN SQL_SMALL_RESULT `a` AS `a` FROM `t`'])]
    #[TestWith(['mysql-8.0.44', 'SELECT SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_BIG_RESULT a FROM t', [SelectOption::BigResult, SelectOption::BufferResult], 'SELECT SQL_BIG_RESULT SQL_BUFFER_RESULT `a` AS `a` FROM `t`'])]
    #[TestWith(['mysql-8.4.7', 'SELECT HIGH_PRIORITY SQL_NO_CACHE a FROM t', [SelectOption::HighPriority, SelectOption::NoCache], 'SELECT HIGH_PRIORITY SQL_NO_CACHE `a` AS `a` FROM `t`'])]
    #[TestWith(['mysql-9.1.0', 'SELECT a FROM t', [], 'SELECT `a` AS `a` FROM `t`'])]
    public function testBindKeepsEachOptionOnceInWrittenOrder(string $release, string $sql, array $options, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (a INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($options, $statement->options);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($options, $rebound->options);
    }

    public function testBindKeepsTheOptionsOfAnInsertedQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, KEY k (a))', 'CREATE TABLE w (a INT)'));
        $statement = $binder->bind('INSERT INTO w SELECT HIGH_PRIORITY a FROM t IGNORE INDEX (k)');
        self::assertInstanceOf(InsertSelectStatement::class, $statement);
        self::assertSame('INSERT INTO `w` SELECT HIGH_PRIORITY `a` AS `a` FROM `t` IGNORE INDEX(`k`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindRejectsBothQueryCacheOptions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::QueryBlockOption->message());
        $binder->bind('SELECT SQL_CACHE SQL_NO_CACHE 1');
    }

    #[TestWith(['mysql-8.4.7', 'SELECT a FROM (SELECT HIGH_PRIORITY a FROM t) AS x'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM t WHERE a IN (SELECT SQL_CALC_FOUND_ROWS a FROM t)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT (SELECT SQL_BUFFER_RESULT a FROM t LIMIT 1)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM t UNION SELECT HIGH_PRIORITY a FROM t'])]
    #[TestWith(['mysql-8.4.7', 'WITH c AS (SELECT HIGH_PRIORITY a FROM t) SELECT a FROM c'])]
    #[TestWith(['mysql-5.7.44', 'SELECT a FROM t UNION SELECT SQL_NO_CACHE a FROM t'])]
    #[TestWith(['mysql-5.6.51', 'SELECT a FROM (SELECT SQL_CACHE a FROM t) AS x'])]
    public function testNestedRejectsFirstBlockOptionsElsewhere(string $release, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (a INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::QueryBlockOption->message());
        $binder->bind($sql);
    }

    #[TestWith(['SELECT HIGH_PRIORITY SQL_BUFFER_RESULT a FROM t UNION SELECT a FROM t'])]
    #[TestWith(['SELECT a FROM (SELECT SQL_NO_CACHE STRAIGHT_JOIN SQL_SMALL_RESULT a FROM t) AS x'])]
    public function testNestedAcceptsOptionsTheServerTakesThere(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
