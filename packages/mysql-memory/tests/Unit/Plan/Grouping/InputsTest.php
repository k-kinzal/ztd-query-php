<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Inputs;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inputs::class)]
#[Small]
final class InputsTest extends TestCase
{
    public function testPlanStoresVaryingWindowGroupsBeforeReadingGroupingLevels(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @y=0');
        $result = $session->query('SELECT SUM(@y:=@y+1),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1'], ['4', '2'], ['6', '3']], $result->rows);
        self::assertSame(3, $session->variables->user('y')[0]);
    }

    public function testStoredKeepsConstantGroupsOnTheDirectRollupPath(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2);SET @y=0');
        $result = $session->query('SELECT SUM(@y:=@y+1),ROW_NUMBER() OVER () FROM t GROUP BY 1+1 WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6', '1'], ['4', '2']], $result->rows);
        self::assertSame(4, $session->variables->user('y')[0]);
    }

    public function testPlanStoresPredicatesAsIntegerFieldsBeforeJsonAggregation(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2)');
        $result = $session->query('SELECT JSON_ARRAYAGG(n>1),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[0]', '1'], ['[1, 1]', '2'], ['[0, 1, 1]', '3']], $result->rows);
    }
    public function testPlanStoresPredicateInputsForOrdinaryWindowGroups(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2)');
        $result = $session->query('SELECT JSON_ARRAYAGG(n>1),ROW_NUMBER() OVER () FROM t GROUP BY n')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[0]', '1'], ['[1, 1]', '2']], $result->rows);
    }
}
