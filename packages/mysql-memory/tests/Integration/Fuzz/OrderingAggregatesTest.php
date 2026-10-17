<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class OrderingAggregatesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'local aggregate' => ['SELECT id FROM t1 ORDER BY SUM(a)'];
        yield 'outer aggregate in scalar query' => ['SELECT id FROM t1 ORDER BY (SELECT SUM(t1.a))'];
        yield 'constant projection' => ['SELECT 1 FROM t1 ORDER BY (SELECT SUM(t1.a))'];
        yield 'second ordering key' => ['SELECT id FROM t1 ORDER BY id, (SELECT SUM(t1.a))'];
        yield 'select already aggregates' => ['SELECT COUNT(*) FROM t1 ORDER BY (SELECT SUM(t1.a))'];
        yield 'select owns nested aggregate' => ['SELECT (SELECT COUNT(t1.a)) FROM t1 ORDER BY (SELECT SUM(t1.a))'];
        yield 'explicit grouping' => ['SELECT id FROM t1 GROUP BY id ORDER BY (SELECT SUM(t1.a)), id'];
        yield 'group sorting by selected alias' => ['SELECT id, SUM(a) AS s FROM t1 GROUP BY id ORDER BY s, id'];
        yield 'ordinary grouped sorting retains keys' => ['SELECT id FROM t1 GROUP BY id ORDER BY a, id'];
        yield 'group sorting by independent subquery' => ['SELECT id FROM t1 GROUP BY id ORDER BY (SELECT SUM(a) FROM t2), id'];
        yield 'HAVING already aggregates' => ['SELECT 1 FROM t1 HAVING COUNT(*)>0 ORDER BY (SELECT SUM(t1.a))'];
        yield 'independent inner aggregate' => ['SELECT id FROM t1 ORDER BY (SELECT SUM(a) FROM t2), id'];
        yield 'constant inner aggregate' => ['SELECT id FROM t1 ORDER BY (SELECT SUM(1)), id'];
        yield 'mode disabled' => ["SET sql_mode=''; SELECT id FROM t1 ORDER BY (SELECT SUM(t1.a))"];
        yield 'mode enabled' => ["SET sql_mode='ONLY_FULL_GROUP_BY'; SELECT id FROM t1 ORDER BY (SELECT SUM(t1.a))"];

    }

    #[DataProvider('providerStatements')]
    public function testOrderingCannotIntroduceAggregationInModernReleases(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
