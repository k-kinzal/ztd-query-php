<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Tuples;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tuples::class)]
#[Small]
final class TuplesTest extends TestCase
{
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
