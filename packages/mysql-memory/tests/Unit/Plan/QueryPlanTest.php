<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryPlan::class)]
#[Small]
final class QueryPlanTest extends TestCase
{
    public function testOriginsAreNoneByDefault(): void
    {
        $plan = new QueryPlan(new ZeroRows(2), [Domain::integer()], ['a']);

        self::assertSame([], $plan->origins);
        self::assertSame(['a'], $plan->names);
    }

    public function testRootRowsMayHoldValuesAfterTheOutputColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 3), (2, 1), (3, 2)');
        $result = $session->query('SELECT a FROM t ORDER BY b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->columns);
        self::assertSame([['2'], ['3'], ['1']], $result->rows);
    }
}
