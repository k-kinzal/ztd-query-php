<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Tuples;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tuples::class)]
#[Small]
final class TuplesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyVersions(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51'];
        yield 'MySQL 5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacyVersions')]
    public function testOmittedFreezesResolvedCountAssignmentsAfterPreparation(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT @x:=2) FROM t WHERE (@x:=@x+1)>0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(5, $session->variables->user('x')[0]);
    }

    public function testPlanReadsLegacyAssignmentsForEveryTupleBeforeNullRejection(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT IF(n=1,NULL,n),@x:=@x+1) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
        self::assertSame(3, $session->variables->user('x')[0]);
    }

    public function testPlanReadsLegacyTuplesInGroupOrderBeforeConcatenation(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT GROUP_CONCAT(@x:=@x+1) FROM t GROUP BY n')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2,3']], $result->rows);
        self::assertSame(3, $session->variables->user('x')[0]);
    }

    public function testPlanRetainsConstantSortKeysWithoutExecutingTheirAssignments(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;CREATE TABLE t(v VARCHAR(2));INSERT INTO t VALUES('b'),('a'),('c');SET @x=0");
        $result = $session->query('SELECT GROUP_CONCAT(v ORDER BY @x:=@x+1) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['c,a,b']], $result->rows);
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testPlanOmitsImplicitDistinctCountConstantsFromRowEvaluation(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT IF(n=1,NULL,n),@x:=@x+1) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testConstantKeepsColumnDependentAssignmentsInTheDistinctTuple(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT @x:=n) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    public function testCheckablePreparesConcatenationConstantsEvenWhenNoRowsSurvive(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);SET @x=0,@y=0');
        $result = $session->query('SELECT GROUP_CONCAT(n,@x:=NULL,@y:=10) FROM t WHERE FALSE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertNull($session->variables->user('x')[0]);
        self::assertSame(10, $session->variables->user('y')[0]);
    }

    public function testCheckableTestsUnmodifiedSessionValuesBeforeConcatenation(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT GROUP_CONCAT(NULLIF(@x,0),n) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testConstantOmitsUnmodifiedSessionValuesFromExplicitCountKeys(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT NULLIF(@x,0)) FROM t GROUP BY n')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['1']], $result->rows);
    }
}
