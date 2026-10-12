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
final class CountPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE count_full(n INT);INSERT INTO count_full VALUES(2),(1),(2);CREATE TEMPORARY TABLE count_empty(n INT);SET @x=0,@y=0;';
        foreach (['count_full', 'count_empty'] as $table) {
            foreach (['COUNT(DISTINCT @x:=2)', 'COUNT(DISTINCT n,@x:=2)', 'COUNT(DISTINCT @x:=NULL,@y:=10)'] as $expression) {
                foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                    foreach (['', ' WHERE FALSE', ' WHERE n<0', ' HAVING FALSE', ' LIMIT 0', ' LIMIT 0 OFFSET 1'] as $bound) {
                        $where = str_starts_with($bound, ' WHERE') ? $bound : '';
                        yield $table . $expression . $group . $bound => [$setup . 'SELECT ' . $expression . ' FROM ' . $table . $where . $group . ($where === '' ? $bound : '') . ';SELECT @x,@y'];
                    }
                }
            }
        }
        foreach (['@x=0', '@x=2', '@x IS NULL', '@x IS NOT NULL', '(@x:=@x+1)>0', '(@y:=1)=1', '(@y:=0)=1'] as $condition) {
            foreach (['WHERE', 'HAVING'] as $clause) {
                yield $clause . $condition => [$setup . 'SELECT COUNT(DISTINCT @x:=2) AS c FROM count_full ' . $clause . ' ' . $condition . ';SELECT @x,@y'];
            }
        }
        $setup = 'CREATE TEMPORARY TABLE count_keys(n INT PRIMARY KEY);INSERT INTO count_keys VALUES(1),(2);SET @x=0,@y=9;';
        foreach (['n=1', 'n=9', 'n=NULL', 'n=@y'] as $condition) {
            foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                yield 'key ' . $condition . $group => [$setup . 'SELECT COUNT(DISTINCT @x:=2) AS c FROM count_keys WHERE ' . $condition . $group . ';SELECT @x,@y'];
            }
        }
        $setup = 'CREATE TEMPORARY TABLE count_outer(n INT);INSERT INTO count_outer VALUES(2),(1),(2);CREATE TEMPORARY TABLE count_inner(n INT);INSERT INTO count_inner VALUES(2),(1);CREATE TEMPORARY TABLE count_outer_empty(n INT);SET @x=0;';
        foreach (['COUNT(DISTINCT @x:=2)', 'COUNT(DISTINCT n,@x:=2)', 'COUNT(DISTINCT @x:=NULL)'] as $expression) {
            foreach (['i.n=o.n', 'i.n=o.n+10', 'FALSE'] as $condition) {
                yield 'correlated ' . $expression . $condition => [$setup . 'SELECT @x:=@x+1 AS b,(SELECT ' . $expression . ' FROM count_inner i WHERE ' . $condition . ') AS c,@x AS a FROM count_outer o'];
            }
        }
        foreach (['count_outer', 'count_outer_empty'] as $table) {
            foreach (['', ' WHERE FALSE', ' WHERE o.n<0', ' HAVING FALSE', ' LIMIT 0', ' LIMIT 0 OFFSET 1'] as $bound) {
                yield 'outer ' . $table . $bound => [$setup . 'SELECT @x:=@x+1 AS b,(SELECT COUNT(DISTINCT @x:=2) FROM count_inner i WHERE i.n=o.n) AS c,@x AS a FROM ' . $table . ' o' . $bound . ';SELECT @x'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testConstantCountInputsArePreparedBeforeEnclosingRows(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
