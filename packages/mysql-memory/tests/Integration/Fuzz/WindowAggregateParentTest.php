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
final class WindowAggregateParentTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE aggregate_outputs(n INT);INSERT INTO aggregate_outputs VALUES(2),(1),(2);';
        foreach (['CONCAT(MIN(n))', 'CONCAT(SUM(n))', 'CONCAT(GROUP_CONCAT(n))', 'CONCAT(JSON_ARRAYAGG(n))', 'CONCAT(JSON_OBJECTAGG(n,n))'] as $expression) {
            foreach (['', 'DISTINCT '] as $distinct) {
                foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                    foreach (['', ',ROW_NUMBER() OVER ()', ',COUNT(*) OVER ()'] as $window) {
                        yield $expression . $distinct . $group . $window => [$setup . 'SELECT ' . $distinct . $expression . $window . ' FROM aggregate_outputs' . $group];
                    }
                }
            }
        }
        foreach (['REPEAT(CONCAT(MIN(n)),600)', 'CONCAT(MIN(CAST(n AS CHAR(600))))', "CONCAT(MIN(REPEAT('x',600)))"] as $expression) {
            foreach (['', 'DISTINCT '] as $distinct) {
                foreach (['', ',ROW_NUMBER() OVER ()'] as $window) {
                    yield 'wide ' . $expression . $distinct . $window => [$setup . 'SELECT ' . $distinct . $expression . $window . ' FROM aggregate_outputs GROUP BY n'];
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
