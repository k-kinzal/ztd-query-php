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
final class GroupedConcatOutputTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE aggregate_outputs(n INT);INSERT INTO aggregate_outputs VALUES(2),(1),(2);';
        foreach (['GROUP_CONCAT(n)', 'GROUP_CONCAT(NULL,n)', 'CONCAT(GROUP_CONCAT(n))'] as $expression) {
            foreach (['', 'DISTINCT '] as $distinct) {
                foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                    foreach (['', ' ORDER BY n DESC'] as $order) {
                        foreach (['', ',ROW_NUMBER() OVER ()'] as $window) {
                            yield $expression . $distinct . $group . $order . $window => [$setup . 'SELECT ' . $distinct . $expression . $window . ' FROM aggregate_outputs' . $group . $order];
                        }
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateOutputPhasesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
