<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Projection\DistinctStorage;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DistinctStorage::class)]
#[Small]
final class DistinctStorageTest extends TestCase
{
    public function testApplyPublishesCopiedAssignmentsInOutputOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(3),(1),(2);SET @x=0');
        $result = $session->query('SELECT DISTINCT @x:=@x+n AS v FROM t ORDER BY v DESC')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6'], ['4'], ['3']], $result->rows);
        self::assertSame('<temporary>', $result->columns[0]->table);
        self::assertSame(3, $session->variables->user('x')[0]);
    }

    public function testApplyRetainsOnlyTheCopiedValueEmittedWithinCalculatedBounds(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;SET NAMES utf8mb4;CREATE TABLE t(d DATE);INSERT INTO t VALUES('2001-01-03'),('2001-01-01'),('2001-01-02');SET @x=0");
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS DISTINCT @x:=d AS v FROM t LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2001-01-01']], $result->rows);
        self::assertSame(0, $result->columns[0]->decimals);
        self::assertSame(3, $session->variables->foundRows);
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];
        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-01', 'utf8mb4']], $state->rows);
    }

    public function testBufferReadsSortingAssignmentsEvenWhenOffsetDiscardsAllRows(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;SET NAMES utf8mb4;CREATE TABLE t(d DATE);INSERT INTO t VALUES('2001-01-03'),('2001-01-01'),('2001-01-02')");
        $session->query('SELECT DISTINCT @x:=d AS v FROM t ORDER BY v LIMIT 1 OFFSET 5');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }

    public function testActiveKeepsEmptyQueriesOnTheirExpressionMetadataPath(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(1);SET @x=0');
        $result = $session->query('SELECT DISTINCT CONCAT(@x:=n) AS v FROM t WHERE 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(31, $result->columns[0]->decimals);
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testApplyPreservesConstantAssignmentExpressionMetadata(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2)');
        $result = $session->query("SELECT DISTINCT @x:='x' AS v FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['x']], $result->rows);
        self::assertSame('', $result->columns[0]->table);
        self::assertSame(31, $result->columns[0]->decimals);
    }

    public function testConstantsCapturesSiblingVariableReadsBeforeDeferredAssignments(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2);SET @x=0');
        $result = $session->query("SELECT DISTINCT @x:='7' AS v,@x AS w FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '0']], $result->rows);
    }

    public function testConstantsDoesNotRunDiscardedAssignmentsWhenCalculatingRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2);SET @x=0');
        $result = $session->query("SELECT SQL_CALC_FOUND_ROWS DISTINCT n,@x:='7' AS v FROM t ORDER BY v LIMIT 0")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(2, $session->variables->foundRows);
        self::assertSame(0, $session->variables->user('x')[0]);
    }
}
