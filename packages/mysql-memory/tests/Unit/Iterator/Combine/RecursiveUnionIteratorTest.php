<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Combine\RecursiveUnionIterator;
use MySqlMemory\Iterator\Source\InlineIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecursiveUnionIterator::class)]
#[Small]
final class RecursiveUnionIteratorTest extends TestCase
{
    public function testInitIteratesUntilAnIterationAddsNoRow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('WITH RECURSIVE r (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r WHERE n < 5) SELECT n FROM r')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2'], ['3'], ['4'], ['5']], $result->rows);
    }

    public function testInitStopsWhenUnionDistinctProducesOnlyRowsSeenBefore(): void
    {
        $session = (new Instance())->connect();
        $anchor = new Inline([[new Constant(Domain::integer(), 1)], [new Constant(Domain::integer(), 1)]], 1);
        $working = new WorkingTable(1);
        $iterator = new RecursiveUnionIterator(new RecursiveUnion($anchor, $working, $working, true, [Domain::integer()], 1000), new InlineIterator($anchor), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], null], [$iterator->read(), $iterator->read()]);
    }

    public function testInitAbortsARecursionDeeperThanTheLimit(): void
    {
        $session = (new Instance())->connect();
        $anchor = new Inline([[new Constant(Domain::integer(), 1)]], 1);
        $working = new WorkingTable(1);
        $iterator = new RecursiveUnionIterator(new RecursiveUnion($anchor, $working, $working, false, [Domain::integer()], 3), new InlineIterator($anchor), new WorkingTableIterator($working));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3636);
        $this->expectExceptionMessage('Recursive query aborted after 4 iterations. Try increasing @@cte_max_recursion_depth to a larger value.');

        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));
    }

    public function testInitReadsTheLimitFromCteMaxRecursionDepth(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET SESSION cte_max_recursion_depth = 10');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Recursive query aborted after 11 iterations. Try increasing @@cte_max_recursion_depth to a larger value.');

        $session->query('WITH RECURSIVE r (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) SELECT n FROM r');
    }

    public function testCollectSkipsRowsSeenBeforeUnderUnionDistinct(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, 'x'], [2, 'y'], [1, 'z']];
        $iterator = new RecursiveUnionIterator(new RecursiveUnion($working, $working, $working, true, [Domain::integer()], 1000), new WorkingTableIterator($working), new WorkingTableIterator($working));
        $seen = [];

        $rows = $iterator->collect(new WorkingTableIterator($working), new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $seen);

        self::assertSame([[1], [2]], $rows);
        self::assertCount(2, $seen);
    }

    public function testCollectKeepsEveryRowUnderUnionAll(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [1]];
        $iterator = new RecursiveUnionIterator(new RecursiveUnion($working, $working, $working, false, [Domain::integer()], 1000), new WorkingTableIterator($working), new WorkingTableIterator($working));
        $seen = [];

        self::assertSame([[1], [1]], $iterator->collect(new WorkingTableIterator($working), new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $seen));
        self::assertSame([], $seen);
    }

    public function testReadAnswersTheAnchorRowsBeforeTheRecursiveOnes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("WITH RECURSIVE r (n, s) AS (SELECT 1, CAST('a' AS CHAR(10)) UNION ALL SELECT n + 1, CONCAT(s, 'b') FROM r WHERE n < 3) SELECT n, s FROM r")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'a'], ['2', 'ab'], ['3', 'abb']], $result->rows);
    }
}
