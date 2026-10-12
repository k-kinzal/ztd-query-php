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
final class AggregateOrderingAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE aggregate_alias_input(a INT); INSERT INTO aggregate_alias_input VALUES(1),(2); SET @x=0; ';
        foreach (['MIN(a)', 'MIN(a)+1', '@x:=MIN(a)', 'SUM(a)'] as $expression) {
            foreach (['', ' GROUP BY a'] as $group) {
                foreach (['v', '(v)', 'ABS(v)', 'v+1'] as $order) {
                    yield $expression . $group . $order => [$setup . 'SELECT ' . $expression . ' AS v FROM aggregate_alias_input' . $group . ' ORDER BY ' . $order . '; SELECT @x'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateAliasResolutionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
