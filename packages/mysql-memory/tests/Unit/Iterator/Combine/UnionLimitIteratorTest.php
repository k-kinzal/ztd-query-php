<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Iterator\Combine\UnionLimitIterator::class)]
#[Small]
final class UnionLimitIteratorTest extends TestCase
{
    public function testReadCountsSkippedAndRemainingTableRowsWithoutProjectingThem(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('USE mysql; CREATE TABLE t(n INT); INSERT INTO t VALUES(1),(2),(2),(3); SET @x=0');
        $table = new \MySqlMemory\Plan\Path\Source\WorkingTable(1);
        $table->rows = [[1], [2], [3]];
        $domain = \MySqlMemory\Typing\Domain::integer();
        $assignment = new \MySqlMemory\Evaluation\Leaf\Assignment('x', new \MySqlMemory\Evaluation\Leaf\ColumnRead($domain, 0), $domain, $domain);
        $path = new \MySqlMemory\Plan\Path\Combine\UnionLimit(new \MySqlMemory\Plan\Path\Transform\Project($table, [$assignment]), 1, 1, true);
        $iterator = (new \MySqlMemory\Iterator\Builder())->build($path);
        $frame = new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator->init($frame);

        self::assertSame([2], $iterator->read());
        self::assertNull($iterator->read());
        self::assertSame(3, $frame->foundBeforeProjection);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    public function testInitRestartsTheInputAndItsCount(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $table = new \MySqlMemory\Plan\Path\Source\WorkingTable(1);
        $table->rows = [[4], [8]];
        $iterator = (new \MySqlMemory\Iterator\Builder())->build(new \MySqlMemory\Plan\Path\Combine\UnionLimit($table, 1, 0, false));
        $frame = new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator->init($frame);
        self::assertSame([4], $iterator->read());
        self::assertNull($iterator->read());
        $iterator->init($frame);

        self::assertSame(0, $frame->foundBeforeProjection);
        self::assertSame([4], $iterator->read());
        self::assertSame(1, $frame->foundBeforeProjection);
    }

    public function testReadCountsOnlyEmittedRowsWhenTheFinalOperandIsConstant(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44'))->connect();
        $session->query('USE mysql; CREATE TABLE t(n INT); INSERT INTO t VALUES(3),(1),(2); SET @x=0');
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS @x:=@x+n AS v FROM t UNION ALL SELECT @x:=@x+1 LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(1, $session->variables->foundRows);
        self::assertSame(1, $session->variables->user('x')[0]);
    }

    public function testReadReportsTableInputAfterExhaustingMixedOperands(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44'))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(3),(1),(2);SET @x=0');
        $result = $session->query('SELECT @x:=@x+1 AS v UNION ALL SELECT @x:=@x+n FROM t LIMIT 1 OFFSET 9')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(3, $session->variables->foundRows);
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testReadExcludesEmittedConstantsFromCalculatedTableInput(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44'))->connect();
        $session->query('USE mysql; CREATE TABLE t(n INT); INSERT INTO t VALUES(3),(1),(2); SET @x=0');
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS @x:=@x+1 AS v UNION ALL SELECT @x:=@x+n FROM t LIMIT 1')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(3, $session->variables->foundRows);
        self::assertSame(1, $session->variables->user('x')[0]);
    }

}
