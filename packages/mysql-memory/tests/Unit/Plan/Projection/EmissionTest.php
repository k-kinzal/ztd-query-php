<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Projection\Emission;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Emission::class)]
#[Small]
final class EmissionTest extends TestCase
{
    public function testPlanKeepsStoredWindowAssignmentsBeforeBounds(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS @x:=ROW_NUMBER() OVER () FROM t LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
        self::assertSame(3, $session->variables->user('x')[0]);
        self::assertSame(3, $session->variables->foundRows);
    }

    public function testBoundedCountsRowsWithoutEvaluatingDiscardedAssignments(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS @x:=@x+n FROM t LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
        self::assertSame(2, $session->variables->user('x')[0]);
        self::assertSame(3, $session->variables->foundRows);
    }

    public function testBoundedConsumesAggregateInputsButSkipsDiscardedParents(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0,@y=0');
        $result = $session->query('SELECT @x:=SUM(@y:=@y+1) AS v FROM t ORDER BY v LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(0, $session->variables->user('x')[0]);
        self::assertSame(3, $session->variables->user('y')[0]);
        self::assertSame(1, $session->variables->foundRows);
    }

    public function testSortedEvaluatesProjectionInOutputOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n AS v FROM t ORDER BY n DESC LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    public function testSortableKeepsChangingAliasValuesBeforeSorting(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n AS v FROM t ORDER BY v DESC LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
        self::assertSame(6, $session->variables->user('x')[0]);
    }
    public function testIndependentFollowsAliasesInsideSortingExpressions(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+1 AS v FROM t ORDER BY ABS(v) LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
        self::assertSame(6, $session->variables->user('x')[0]);
    }

}
