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
final class AggregateDemandTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $tails = ['HAVING 0', 'LIMIT 0', 'LIMIT 0 OFFSET 2', 'GROUP BY id HAVING 0', 'ORDER BY 1 LIMIT 0', 'WHERE 0', 'HAVING COUNT(*)>99'];
        foreach (['SUM(1/0)', "SUM(CAST('x' AS SIGNED))"] as $expression) {
            foreach ($tails as $tail) {
                yield $expression . ' ' . $tail => ['SELECT ' . $expression . ' FROM t1 ' . $tail];
            }
        }
        foreach ($tails as $tail) {
            yield 'assignment ' . $tail => ['SET @v=0; SELECT SUM(@v:=@v+1) FROM t1 ' . $tail . '; SELECT @v'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregationRunsOnlyWhenItsRowsAreRequested(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
