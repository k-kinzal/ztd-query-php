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
final class WindowDemandTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['', 'ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', 'ORDER BY a ROWS BETWEEN CURRENT ROW AND CURRENT ROW', 'ORDER BY a ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING', 'ORDER BY a ROWS BETWEEN 2 FOLLOWING AND 2 FOLLOWING'] as $window) {
            foreach (['FIRST_VALUE(@x:=@x+1)', 'FIRST_VALUE((@x:=@x+1)+a)', 'SUM(@x:=@x+1)', 'SUM(1/0)', 'FIRST_VALUE(1/0)'] as $function) {
                foreach ([' LIMIT 0', ' LIMIT 1', ' LIMIT 1 OFFSET 1', ''] as $tail) {
                    $sql = 'SELECT ' . $function . ' OVER (' . $window . ') AS v FROM (SELECT 1 a UNION ALL SELECT 2 UNION ALL SELECT 3) t' . $tail;
                    yield $sql => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO';SET @x=0;" . $sql . ';SHOW WARNINGS;SELECT @x'];
                }
            }
            foreach (['FIRST_VALUE(CAST((@x:=@x+1) AS SIGNED))', 'FIRST_VALUE(@x:=a)', 'LAST_VALUE(@x:=@x+a)', 'AVG(@x:=@x+1)'] as $function) {
                $sql = 'SELECT ' . $function . ' OVER (' . $window . ') AS v FROM (SELECT 1 a UNION ALL SELECT 2 UNION ALL SELECT 3) t';
                yield $sql => ['SET @x=0;' . $sql . ';SHOW WARNINGS;SELECT @x'];
            }
        }
        yield from self::providerPartitions();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPartitions(): iterable
    {
        foreach (['ORDER BY a', 'ORDER BY a DESC', 'PARTITION BY a%2 ORDER BY a', 'ORDER BY a RANGE BETWEEN CURRENT ROW AND CURRENT ROW', 'ORDER BY a RANGE BETWEEN 1 PRECEDING AND CURRENT ROW', 'ORDER BY a ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING'] as $window) {
            foreach (['FIRST_VALUE(@x:=@x+1)', 'LAST_VALUE(@x:=@x+1)', 'SUM(@x:=@x+1)', 'LAG(@x:=@x+1)', 'LEAD(@x:=@x+1)'] as $function) {
                foreach ([' LIMIT 1', ''] as $tail) {
                    $sql = 'SELECT a,' . $function . ' OVER (' . $window . ') AS v FROM (SELECT 3 a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 2) t' . $tail;
                    yield $sql => ['SET @x=0;' . $sql . ';SHOW WARNINGS;SELECT @x'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testWindowDemandPreservesArgumentsWarningsAndVariableState(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
