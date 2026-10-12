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
final class SeededRandomOrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE random_order(n INT);INSERT INTO random_order VALUES(1),(2),(3);SET @x=0;';
        foreach (['RAND(1) AS v', '@x:=@x+n AS w,RAND(1) AS v', 'RAND(1) AS v,@x:=@x+n AS w', 'RAND(1) AS v,RAND(2) AS w', 'RAND(2) AS w,RAND(1) AS v', '@x:=RAND(1) AS v'] as $projection) {
            foreach (['v', 'ABS(v)', 'ABS(v) DESC', 'ABS(v)+ABS(v)'] as $key) {
                foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 0'] as $bounds) {
                    yield $projection . ' ' . $key . $bounds => [$setup . 'SELECT ' . $projection . ' FROM random_order ORDER BY ' . $key . $bounds . ';SELECT @x'];
                }
            }
        }
        foreach (['RAND(1) AS v,RAND(2) AS w', 'RAND(2) AS w,RAND(1) AS v'] as $projection) {
            foreach (['ABS(v),ABS(w)', 'ABS(w),ABS(v)'] as $keys) {
                foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 0'] as $bounds) {
                    yield $projection . ' ' . $keys . $bounds => [$setup . 'SELECT ' . $projection . ' FROM random_order ORDER BY ' . $keys . $bounds . ';SELECT @x'];
                }
            }
        }
        $setup = 'CREATE TEMPORARY TABLE random_order(n INT);INSERT INTO random_order VALUES(3),(1),(2);SET @x=10,@y=0;';
        foreach (['@x:=n AS v', '@x:=@x-n AS v', 'ABS(@x:=RAND(1)) AS v', '@x:=@x-n AS w,n+@x AS v', 'n+@x AS v,@x:=@x-n AS w', '@y:=@y+1 AS w,@x:=RAND(1) AS v'] as $projection) {
            foreach (['ABS(v)', 'ABS(v) DESC', 'ABS(v)+ABS(v)'] as $key) {
                foreach (['', ' LIMIT 1 OFFSET 1'] as $bounds) {
                    yield 'retained fields ' . $projection . ' ' . $key . $bounds => [$setup . 'SELECT ' . $projection . ' FROM random_order ORDER BY ' . $key . $bounds . ';SELECT @x,@y'];
                }
            }
        }

    }

    #[DataProvider('providerStatements')]
    public function testRandomDrawsAndAssignmentsFollowTheNativeEvaluationOrder(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
