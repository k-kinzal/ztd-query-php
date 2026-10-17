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
final class GroupedNullDistinctTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET sql_mode='';CREATE TEMPORARY TABLE distinct_nulls(n INT);";
        foreach (['(0),(NULL),(1)', '(NULL),(0),(0),(-1)'] as $rows) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['n', 'ABS(n)', 'SUM(n)', 'MIN(n)', 'CAST(n AS DECIMAL(5,1))', 'n+0e0', 'CONCAT(n)', 'ABS(n),COUNT(*)', 'ABS(n),n'] as $field) {
                    yield $rows . $group . $field => [$setup . 'INSERT INTO distinct_nulls VALUES' . $rows . ';SELECT DISTINCT ' . $field . ' FROM distinct_nulls' . $group];
                }
            }
        }
        foreach (['(0),(NULL),(1)', '(NULL),(0),(1)'] as $rows) {
            foreach (['n', 'ABS(n)'] as $field) {
                foreach ([' LIMIT 1', ' ORDER BY MIN(1)'] as $order) {
                    yield $rows . $field . $order => [$setup . 'INSERT INTO distinct_nulls VALUES' . $rows . ';SELECT DISTINCT ' . $field . ' FROM distinct_nulls GROUP BY n' . $order];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testGroupedNullDistinctMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
