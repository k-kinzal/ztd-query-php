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
final class RollupWindowDistinctTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TEMPORARY TABLE rollup_window_distinct(n INT,s VARCHAR(5)); INSERT INTO rollup_window_distinct VALUES(1,'a'),(2,'a'),(3,'b'),(4,'b'); ";
        foreach (['CONCAT(MIN(MIN(s)) OVER ())', 'MIN(MIN(s)) OVER ()', 'COALESCE(MIN(MIN(n)) OVER (),0)', 'MIN(MIN(n)) OVER ()+0', 'ABS(MIN(MIN(n)) OVER ())', 'CONCAT(MIN(MIN(s)) OVER ()), MIN(MIN(s)) OVER ()', 'CONCAT(MIN(MIN(s)) OVER ()), 1', 'CONCAT(MIN(MIN(s)) OVER ()), MIN(s)', 'CONCAT(MIN(s)), MIN(MIN(s)) OVER ()', 'CONCAT(MIN(MIN(s)) OVER ()), CONCAT(MIN(s))', 'CONCAT(MIN(n) OVER ())', 'CONCAT(MIN(s))', 'MIN(MIN(n)) OVER ()+MAX(MAX(n)) OVER ()'] as $value) {
            foreach (['GROUP BY n WITH ROLLUP', 'GROUP BY s WITH ROLLUP', 'GROUP BY n,s WITH ROLLUP'] as $group) {
                yield $value . ' ' . $group => [$setup . 'SELECT DISTINCT ' . $value . ' AS v FROM rollup_window_distinct ' . $group];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testDeferredParentsAndStoredDistinctKeysMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
