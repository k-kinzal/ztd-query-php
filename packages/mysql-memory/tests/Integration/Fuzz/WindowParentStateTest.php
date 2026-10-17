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
final class WindowParentStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
            foreach (['', 'DISTINCT '] as $distinct) {
                yield 'json assignment ' . $distinct . $group => ['CREATE TEMPORARY TABLE window_parent_json(n INT); INSERT INTO window_parent_json VALUES(1),(2); SET @x=NULL; SELECT ' . $distinct . "@x:=FIRST_VALUE(CAST('[1]' AS JSON)) OVER () AS v FROM window_parent_json" . $group . '; SELECT @x'];
            }
        }
        $extra = 'CREATE TEMPORARY TABLE window_parent_extra(n INT); INSERT INTO window_parent_extra VALUES(1),(2),(3); SET @x=0; ';
        foreach (['@x:=@x+n+0*ROW_NUMBER() OVER () AS v', 'ABS(@x:=@x+n+0*ROW_NUMBER() OVER ()) AS v', '@x:=@x+n+0*ROW_NUMBER() OVER () AS v, @x AS w', '@x AS w, @x:=@x+n+0*ROW_NUMBER() OVER () AS v'] as $expression) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', ' ORDER BY v DESC LIMIT 2', ' ORDER BY v DESC LIMIT 0', ' ORDER BY v DESC LIMIT 1 OFFSET 4', ' LIMIT 1 OFFSET 1'] as $tail) {
                    yield 'output ' . $expression . $group . $tail => [$extra . 'SELECT ' . $expression . ' FROM window_parent_extra' . $group . $tail . '; SELECT @x'];
                }
            }
        }
        $setup = 'CREATE TEMPORARY TABLE window_parent_frontier(n INT); INSERT INTO window_parent_frontier VALUES(1),(2),(3); SET @x=0; ';
        foreach (['ROW_NUMBER() OVER ()', 'SUM(n) OVER (ROWS CURRENT ROW)', 'SUM(n) OVER (ORDER BY n ROWS UNBOUNDED PRECEDING)', 'SUM(n) OVER (ORDER BY n ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING)', 'COUNT(*) OVER ()', 'SUM(SUM(n)) OVER (ROWS CURRENT ROW)'] as $window) {
            foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', ' ORDER BY v', ' ORDER BY v DESC LIMIT 2'] as $tail) {
                    yield $window . $group . $tail => [$setup . 'SELECT @x:=@x+n+0*' . $window . ' AS v FROM window_parent_frontier' . $group . $tail . '; SELECT @x'];
                }
            }
        }
        $setup = "CREATE TEMPORARY TABLE window_parent_distinct(n INT,s VARCHAR(5)); INSERT INTO window_parent_distinct VALUES(1,'a'),(2,'a'),(3,'b'),(4,'b'); SET @x=0; ";
        foreach (['@x:=@x+1+0*MIN(MIN(n)) OVER () AS v', '@x:=@x+1+0*MIN(MIN(n)) OVER () AS v, MIN(s)', '@x:=@x+1+0*MIN(MIN(n)) OVER () AS v, MIN(MIN(s)) OVER ()', 'MIN(s), @x:=@x+1+0*MIN(MIN(n)) OVER () AS v'] as $value) {
            foreach (['', ' ORDER BY v', ' LIMIT 1', ' ORDER BY v LIMIT 1'] as $tail) {
                yield 'distinct ' . $value . $tail => [$setup . 'SELECT DISTINCT ' . $value . ' FROM window_parent_distinct GROUP BY n WITH ROLLUP' . $tail . '; SELECT @x'];
            }
        }
        foreach (['MIN(MIN(n)) OVER ()', 'MIN(n) OVER ()', 'ROW_NUMBER() OVER ()'] as $window) {
            foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'capture ' . $distinct . $window . $group => [$setup . 'SELECT ' . $distinct . '@x:=@x+1+0*' . $window . ' AS v FROM window_parent_distinct' . $group . '; SELECT @x'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testCapturedInputsAndEmittedAssignmentsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
