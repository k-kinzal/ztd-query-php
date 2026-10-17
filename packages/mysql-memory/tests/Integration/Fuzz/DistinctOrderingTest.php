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
final class DistinctOrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE distinct_order_inputs(n INT);INSERT INTO distinct_order_inputs VALUES(1),(2),(2),(NULL);';
        foreach (['', "SET sql_mode='';", "SET sql_mode='ONLY_FULL_GROUP_BY';"] as $mode) {
            foreach (['n', 'ABS(n)', 'SUM(n)', 'GROUP_CONCAT(n)', 'COUNT(*)', 'CONCAT(MIN(n))', '1'] as $field) {
                foreach (['n', 'SUM(n)', 'MIN(n)', 'GROUP_CONCAT(n)', 'SUM(n)+1', 'ABS(n)', 'n+SUM(n)', 'SUM(n)+n', 'MIN(1)'] as $order) {
                    foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                        yield $mode . $field . $group . $order => [$mode . $setup . 'SELECT DISTINCT ' . $field . ' FROM distinct_order_inputs' . $group . ' ORDER BY ' . $order];
                    }
                }
            }
            foreach (['SUM(n)', 'COUNT(*)', '1'] as $field) {
                foreach (['SUM(n)', 'COUNT(*)', 'n'] as $order) {
                    yield $mode . $field . $order => [$mode . $setup . 'SELECT DISTINCT ' . $field . ' FROM distinct_order_inputs ORDER BY ' . $order];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerImplicitInputs(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE distinct_order_inputs(n INT);INSERT INTO distinct_order_inputs VALUES(1),(2),(NULL);';
        foreach (["SET sql_mode='';", "SET sql_mode='ONLY_FULL_GROUP_BY';"] as $mode) {
            foreach (['', 'DISTINCT '] as $distinct) {
                foreach (['SUM(n) total', 'COUNT(*) total'] as $field) {
                    foreach (['n', 'ABS(n)', 'SUM(n)', 'total'] as $order) {
                        yield 'implicit ' . $mode . $distinct . $field . $order => [$mode . $setup . 'SELECT ' . $distinct . $field . ' FROM distinct_order_inputs ORDER BY ' . $order];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    #[DataProvider('providerImplicitInputs')]
    public function testDistinctOrderingMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
