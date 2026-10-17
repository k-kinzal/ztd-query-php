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
final class EmptyRollupTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE empty_rollup(n INT PRIMARY KEY);';
        foreach (['n', 'n,COUNT(*)', 'n,SUM(n)', 'n,GROUP_CONCAT(n)', 'n,GROUPING(n)'] as $expression) {
            foreach (['', ' WHERE FALSE', ' WHERE n<0', ' WHERE n=1'] as $condition) {
                yield $expression . $condition => [$setup . 'SELECT ' . $expression . ' FROM empty_rollup' . $condition . ' GROUP BY n WITH ROLLUP'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyRollupTotalsFollowTheReleaseAndInputPruning(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
