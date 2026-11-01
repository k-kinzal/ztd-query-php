<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Window\Direct;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Direct::class)]
#[Small]
final class DirectTest extends TestCase
{
    public function testBindKeepsIndependentAggregateFoldsAcrossConstantRollupRows(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT COUNT(*) OVER (),SUM(10) OVER (),ROW_NUMBER() OVER () FROM DUAL GROUP BY 1+1 WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10', '1'], ['2', '20', '1']], $result->rows);
    }

    public function testBindChecksFrameBoundariesBeforeDirectEvaluation(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);

        $session->query('SELECT FIRST_VALUE(10) OVER (ROWS BETWEEN 1 FOLLOWING AND 1 PRECEDING)');
    }

    public function testBoundedCountsDiscardedInputWithoutFoldingSkippedWindowValues(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT COUNT(*) OVER () FROM DUAL GROUP BY 1+1 WITH ROLLUP LIMIT 1 OFFSET 1')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(2, $session->variables->foundRows);

        $empty = $session->query('SELECT SQL_CALC_FOUND_ROWS COUNT(*) OVER () FROM DUAL GROUP BY 1+1 WITH ROLLUP LIMIT 0')[0];
        self::assertInstanceOf(ResultSet::class, $empty);
        self::assertSame([], $empty->rows);
        self::assertSame(2, $session->variables->foundRows);
    }

    public function testBoundedSkipsWindowArgumentsAfterAnExhaustedTablelessOffsetButKeepsFilterWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'");
        $result = $session->query('SELECT FIRST_VALUE(1/0) OVER () LIMIT 1 OFFSET 1')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertCount(0, $session->diagnostics->conditions);

        $session->query('SELECT FIRST_VALUE(1/0) OVER () WHERE 1/0 LIMIT 1 OFFSET 1');
        self::assertCount(1, $session->diagnostics->conditions);
    }

    public function testConstantLeavesMultipleInputRowsInTheirWindowPartition(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT COUNT(*) OVER () FROM (SELECT 1 UNION ALL SELECT 2) t GROUP BY 1+1 WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2'], ['2']], $result->rows);
    }
}
