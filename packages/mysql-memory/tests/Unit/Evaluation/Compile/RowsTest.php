<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Rows;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testElementsReadsARowConstructorWrittenInParentheses(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ((1, 2)) = (1, 2), ROW(1, 2) = (1, 2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testTupleRoutesComparisonsBetweenTwoMultiColumnSubqueries(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT 1,2) = (SELECT 1,2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testSubqueryRecognizesGroupingAroundARowSubquery(): void
    {
        $result = (new Instance())->connect()->query('SELECT ((SELECT 1,2)) = (1,2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testOperandBindsRowSubqueriesToTheirOuterScope(): void
    {
        $result = (new Instance())->connect()->query('SELECT id, (id,v) = (SELECT id,20) FROM (SELECT 1 id,10 v UNION ALL SELECT 2,20) t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0'], ['2', '1']], $result->rows);
    }

    public function testSubqueriedUsesTheQuietStringDomainOfEachSubqueryColumn(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT (1,0) = (SELECT 1,'x')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testCompareComparesTwoRowsElementByElement(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, 2) = (1, 2), (1, 2) < (1, 3), (2, 1) < (1, 3), (1, NULL) = (1, 2), (1, NULL) = (2, 2), (1, NULL) <=> (1, NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', null, '0', '1']], $result->rows);
    }

    public function testCompareReadsTheColumnsOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20)');
        $result = $session->query('SELECT id FROM t WHERE (id, v) = (2, 20)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testPairedComparesNestedRowsInDepthFirstOrder(): void
    {
        $result = (new Instance())->connect()->query('SELECT ROW(ROW(1,2),3) = ROW(ROW(1,2),3), ROW(ROW(1,2),3) < ROW(ROW(2,1),2), ROW(ROW(1,NULL),3) = ROW(ROW(1,2),4)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0']], $result->rows);
    }

    public function testPairedRefusesDifferentNestedShapes(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');

        $session->query('SELECT ROW(ROW(1,2),3) = ROW(1,ROW(2,3))');
    }

    public function testCompareRefusesRowsOfDifferentWidths(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');

        $session->query('SELECT (1, 2) = (1, 2, 3)');
    }

    public function testInComparesJsonTextWithinRowsWithOnePreparationWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT (CAST('[1]' AS JSON),1) IN ((0,1),(2,2))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertSame([['Warning', 1235, "This version of MySQL doesn't yet support 'comparison of JSON within a ROW in the IN operator'"]], $session->diagnostics->conditions);
    }

    public function testInTellsWhetherARowIsInAListOfRows(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, 2) IN ((3, 4), (1, 2)), (1, 2) NOT IN ((3, 4)), (1, NULL) IN ((1, 2)), (1, NULL) IN ((2, 2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null, '0']], $result->rows);
    }
}
