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
final class RollupAggregateInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE rollup_inputs(n INT,b INT);INSERT INTO rollup_inputs VALUES(2,1),(1,1),(2,2);SET @y=0;';
        foreach (['SUM(@y:=@y+1) AS v', 'SUM(@y:=@y+1) AS v,ROW_NUMBER() OVER () AS w', 'FIRST_VALUE(SUM(@y:=@y+1)) OVER () AS v', 'SUM(@y:=@y+1) AS v,SUM(@y:=@y+10) AS w'] as $projection) {
            foreach (['n', 'n%2', 'n,b', '1+1', '1+1,n', 'n,1+1'] as $groups) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    foreach (['', ' ORDER BY v'] as $order) {
                        yield $projection . $groups . $distinct . $order => [$setup . 'SELECT ' . $distinct . $projection . ' FROM rollup_inputs GROUP BY ' . $groups . ' WITH ROLLUP' . $order . ';SELECT @y'];
                    }
                }
            }
        }
        foreach (['JSON_ARRAYAGG(n>1)', 'JSON_OBJECTAGG(b,n>1)', "GROUP_CONCAT(@y:=@y+1 ORDER BY n DESC SEPARATOR ':')", 'COUNT(DISTINCT @y:=@y+1)', 'SUM(DISTINCT @y:=@y+1)', 'MIN(@y:=@y+1)', 'MAX(@y:=@y+1)'] as $aggregate) {
            foreach (['', ',ROW_NUMBER() OVER () AS w'] as $window) {
                yield $aggregate . $window => [$setup . 'SELECT ' . $aggregate . ' AS v' . $window . ' FROM rollup_inputs GROUP BY n WITH ROLLUP;SELECT @y'];
            }
        }
        foreach (['SUM(@y:=@y+1),COUNT(DISTINCT @y:=@y+10)', 'COUNT(DISTINCT @y:=@y+10),SUM(@y:=@y+1)', 'SUM(@y:=@y+1),GROUP_CONCAT(@y:=@y+10)', 'GROUP_CONCAT(@y:=@y+10),SUM(@y:=@y+1)', 'AVG(@y:=@y+1)', 'COUNT(@y:=@y+1)', 'JSON_ARRAYAGG(@y:=@y+1)'] as $projection) {
            yield 'shared ' . $projection => [$setup . 'SELECT ' . $projection . ' FROM rollup_inputs GROUP BY n WITH ROLLUP;SELECT @y'];
        }
        $setup = 'CREATE TEMPORARY TABLE rollup_inputs(n INT);INSERT INTO rollup_inputs VALUES(1),(2);SET @y=0;';
        foreach (['SUM(@y:=@y+1)', 'FIRST_VALUE(SUM(@y:=@y+1)) OVER ()', 'LAST_VALUE(SUM(@y:=@y+1)) OVER ()', 'SUM(SUM(@y:=@y+1)) OVER ()', 'MIN(SUM(@y:=@y+1)) OVER ()'] as $projection) {
            foreach (['', ' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'parent ' . $projection . $group . $distinct => [$setup . 'SELECT ' . $distinct . $projection . ' AS v FROM rollup_inputs' . $group . ';SELECT @y'];
                }
            }
        }
        $setup = "CREATE TEMPORARY TABLE concat_ties(v VARCHAR(2),k INT);INSERT INTO concat_ties VALUES('a',1),('b',1),('c',2),('d',2),('a',1);";
        foreach (['', 'DISTINCT '] as $distinct) {
            foreach (['k', 'k DESC', 'k,v', '1+1', '1+1 DESC'] as $order) {
                yield 'ties ' . $distinct . $order => [$setup . 'SELECT GROUP_CONCAT(' . $distinct . 'v ORDER BY ' . $order . ') FROM concat_ties'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateInputsExecuteInTheirGroupingPhase(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
