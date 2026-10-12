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
final class RollupInputOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TEMPORARY TABLE rollup_direction(n INT,k INT,v VARCHAR(2));INSERT INTO rollup_direction VALUES(2,1,'a'),(1,2,'b'),(2,2,'c'),(1,1,'d'),(2,1,'e'),(NULL,1,'z');SET @x=0;";
        foreach (['n', 'n,k'] as $group) {
            foreach (['GROUP_CONCAT(v,@x:=@x+1)', 'SUM(@x:=@x+1)'] as $expression) {
                foreach (['', ',ROW_NUMBER() OVER ()'] as $window) {
                    foreach (['', 'DISTINCT '] as $distinct) {
                        $orders = $group === 'n' ? ['', 'n DESC', 'n ASC', 'n+0 DESC', 'GROUPING(n),n DESC', 'GROUPING(n) DESC,n DESC', '1 DESC'] : ['', 'n DESC', 'n DESC,k ASC', 'n ASC,k DESC', 'n DESC,k DESC', 'k DESC,n ASC', 'k DESC', 'GROUPING(n,k),n DESC', '1 DESC,2 ASC'];
                        foreach ($orders as $order) {
                            yield $group . $expression . $window . $distinct . $order => [$setup . 'SELECT ' . $distinct . $group . ',' . $expression . ',GROUPING(' . $group . ')' . $window . ' FROM rollup_direction GROUP BY ' . $group . ' WITH ROLLUP' . ($order === '' ? '' : ' ORDER BY ' . $order) . ';SELECT @x'];
                        }
                    }
                }
            }
        }
        foreach (['1+1,n', 'n,1+1', 'n,n', 'n,k,n', 'n,k'] as $group) {
            foreach (['n DESC', 'n DESC,n ASC', 'n DESC,k ASC,n DESC', 'n DESC,k+0 DESC', 'n DESC,GROUPING(n) DESC'] as $order) {
                yield 'prefix ' . $group . $order => [$setup . 'SELECT n,GROUP_CONCAT(v,@x:=@x+1),GROUPING(n) FROM rollup_direction GROUP BY ' . $group . ' WITH ROLLUP ORDER BY ' . $order . ';SELECT @x'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testCompatibleOutputOrderingControlsRollupInputs(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
