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
final class GroupedDistinctStorageTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET sql_mode='';CREATE TEMPORARY TABLE distinct_shapes(n INT);INSERT INTO distinct_shapes VALUES(2),(1),(2),(NULL),(0),(-1);";
        foreach (['ABS(n)', 'SUM(n)', 'n*2', 'GROUP_CONCAT(-n)', 'CONCAT(SUM(n))', 'ABS(n),COUNT(*)', 'COUNT(*),ABS(n)', '1', 'ABS(n),ROW_NUMBER() OVER ()'] as $field) {
            foreach (['', ' WITH ROLLUP'] as $rollup) {
                foreach (['', ' ORDER BY MIN(1)', ' ORDER BY COUNT(*) DESC', ' ORDER BY MIN(n) DESC'] as $order) {
                    yield $field . $rollup . $order => [$setup . 'SELECT DISTINCT ' . $field . ' FROM distinct_shapes GROUP BY n' . $rollup . $order];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testGroupedDistinctStorageMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
