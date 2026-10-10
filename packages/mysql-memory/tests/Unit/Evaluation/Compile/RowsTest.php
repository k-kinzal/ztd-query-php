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

    public function testCompareRefusesRowsOfDifferentWidths(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');

        $session->query('SELECT (1, 2) = (1, 2, 3)');
    }

    public function testInTellsWhetherARowIsInAListOfRows(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, 2) IN ((3, 4), (1, 2)), (1, 2) NOT IN ((3, 4)), (1, NULL) IN ((1, 2)), (1, NULL) IN ((2, 2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null, '0']], $result->rows);
    }
}
