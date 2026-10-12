<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Window\DistinctOutputs;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DistinctOutputs::class)]
#[Small]
final class DistinctOutputsTest extends TestCase
{
    public function testPlanDefersAggregateParentsAndComparesTheRemainingStoredFields(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; CREATE TEMPORARY TABLE t(n INT,s VARCHAR(5)); INSERT INTO t VALUES(1,'a'),(2,'a'),(3,'b'),(4,'b')");
        $deferred = $session->query('SELECT DISTINCT CONCAT(MIN(MIN(s)) OVER ()) FROM t GROUP BY n WITH ROLLUP')[0];
        $keyed = $session->query('SELECT DISTINCT CONCAT(MIN(MIN(s)) OVER ()),MIN(s) FROM t GROUP BY n WITH ROLLUP')[0];
        self::assertInstanceOf(ResultSet::class, $deferred);
        self::assertInstanceOf(ResultSet::class, $keyed);

        self::assertSame([['a'], ['a'], ['a'], ['a'], ['a']], $deferred->rows);
        self::assertSame([['a', 'a'], ['a', 'b']], $keyed->rows);
    }
}
