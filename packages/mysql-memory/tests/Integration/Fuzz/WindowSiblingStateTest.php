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
final class WindowSiblingStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE window_sibling(n INT); INSERT INTO window_sibling VALUES(1),(2),(3); SET @x=0; ';
        foreach (['@x:=@x+1 AS v,ROW_NUMBER() OVER () AS w', 'ROW_NUMBER() OVER () AS w,@x:=@x+1 AS v', '@x:=@x+1 AS v,FIRST_VALUE(@x) OVER () AS w', '@x:=@x+1 AS v,SUM(@x) OVER () AS w', '@x:=@x+1 AS v,@x:=@x+10+0*ROW_NUMBER() OVER () AS w'] as $value) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                yield $value . $group => [$setup . 'SELECT ' . $value . ' FROM window_sibling' . $group . '; SELECT @x'];
            }
        }
        yield from self::providerBounds();
        yield from self::providerInteractions();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBounds(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE window_sibling_bounds(n INT); INSERT INTO window_sibling_bounds VALUES(1),(2),(3); SET @x=0; ';
        foreach (['@x:=@x+1 AS v,SUM(@x) OVER (ROWS CURRENT ROW) AS w', '@x:=@x+1 AS v,SUM(@x) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS w', '@x:=@x+1 AS v,FIRST_VALUE(@x) OVER (ORDER BY n) AS w', 'ABS(@x:=@x+1) AS v,ROW_NUMBER() OVER () AS w', '@x:=@x+1 AS v,@x:=@x+10+0*SUM(n) OVER () AS w', '@x:=@x+10+0*ROW_NUMBER() OVER () AS w,@x:=@x+1 AS v'] as $value) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', ' LIMIT 1 OFFSET 1', ' ORDER BY v DESC LIMIT 2', ' ORDER BY v DESC LIMIT 0'] as $tail) {
                    yield 'bounds ' . $value . $group . $tail => [$setup . 'SELECT ' . $value . ' FROM window_sibling_bounds' . $group . $tail . '; SELECT @x'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInteractions(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE window_sibling_interaction(n INT); INSERT INTO window_sibling_interaction VALUES(1),(2),(3); SET @x=0; ';
        foreach (['@x:=@x+1 AS v,FIRST_VALUE(@x) OVER (PARTITION BY n%2 ORDER BY n) AS w', '@x:=@x+1 AS v,SUM(@x) OVER (PARTITION BY n%2 ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS w', '@x:=@x+1 AS v,@x AS r,ROW_NUMBER() OVER () AS w', '@x:=@x+1 AS v,@x:=@x+10 AS z,ROW_NUMBER() OVER () AS w', '@x:=@x+1 AS v,ROW_NUMBER() OVER (ORDER BY n) AS w,ROW_NUMBER() OVER (ORDER BY n DESC) AS z', '@x:=@x+1 AS v,FIRST_VALUE(@x:=@x+10) OVER () AS w'] as $value) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', ' LIMIT 1 OFFSET 1'] as $tail) {
                    yield 'interaction ' . $value . $group . $tail => [$setup . 'SELECT ' . $value . ' FROM window_sibling_interaction' . $group . $tail . '; SELECT @x'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testOrdinaryAssignmentsAndWindowInputsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
