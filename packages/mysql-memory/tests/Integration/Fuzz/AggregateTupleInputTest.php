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
final class AggregateTupleInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE aggregate_tuple_input(n INT);INSERT INTO aggregate_tuple_input VALUES(2),(1),(2);SET @x=0,@y=0;';
        foreach (['COUNT(DISTINCT NULL,@x:=@x+1)', 'COUNT(DISTINCT @x:=@x+1,NULL)', 'COUNT(DISTINCT @x:=@x+1,CAST(NULL AS SIGNED))', 'COUNT(DISTINCT IF(n=1,NULL,n),@x:=@x+1)', 'COUNT(DISTINCT @x:=@x+1,IF(n=1,NULL,n))', 'GROUP_CONCAT(NULL,@x:=@x+1 ORDER BY @x:=@x+10)', 'GROUP_CONCAT(@x:=@x+1,NULL ORDER BY @x:=@x+10)', 'GROUP_CONCAT(IF(n=1,NULL,n),@x:=@x+1 ORDER BY @x:=@x+10)', 'GROUP_CONCAT(@x:=@x+1,IF(n=1,NULL,n) ORDER BY @x:=@x+10)'] as $expression) {
            foreach (['', ' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', ',ROW_NUMBER() OVER ()'] as $window) {
                    yield 'nullable ' . $expression . $group . $window => [$setup . 'SELECT ' . $expression . $window . ' FROM aggregate_tuple_input' . $group . ';SELECT @x,@y'];
                }
            }
        }
        foreach (['GROUP_CONCAT(NULLIF(@x,0),n)', 'GROUP_CONCAT(@x:=NULLIF(@x,0),n)', 'COUNT(DISTINCT NULLIF(@x,0))', 'COUNT(DISTINCT @x)', 'COUNT(DISTINCT @x:=@x+1)', 'COUNT(DISTINCT @x:=n)', 'COUNT(DISTINCT @x:=NULL)', 'COUNT(DISTINCT n,@x:=@x+1)', 'COUNT(DISTINCT n,@x:=2)', 'COUNT(DISTINCT n,@x:=NULL)', 'COUNT(DISTINCT n,@x:=NULLIF(@x,0))', 'COUNT(DISTINCT n,@x:=IF(@x=0,NULL,1))', 'COUNT(DISTINCT @x:=@x+1,@y:=@y+10)', 'COUNT(DISTINCT @x:=NULL,n,@y:=@y+10)', 'COUNT(DISTINCT @x:=@x+1,NULLIF(1,1))', 'GROUP_CONCAT(@x:=@x+1)', 'GROUP_CONCAT(@x:=NULL,n,@y:=@y+10)', 'GROUP_CONCAT(@x:=@x+1,IF(n=1,NULL,n) ORDER BY n)', 'GROUP_CONCAT(IF(n=1,NULL,n),@x:=@x+1 ORDER BY @y:=n)', 'GROUP_CONCAT(@x:=IF(n=1,NULL,n),@y:=@y+10)', 'GROUP_CONCAT(@x:=@x+1,IF(n=1,NULL,n) ORDER BY @y:=@y+10+n)', 'GROUP_CONCAT(@x:=@x+1,NULLIF(1,1))', 'COUNT(DISTINCT IF(n=1,NULL,n),@x:=@x+1,@y:=@y+10)', 'GROUP_CONCAT(IF(n=1,NULL,n),@x:=@x+1,@y:=@y+10)'] as $expression) {
            foreach (['', ' GROUP BY 1+1', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                yield 'constant ' . $expression . $group => [$setup . 'SELECT ' . $expression . ' FROM aggregate_tuple_input' . $group . ';SELECT @x,@y'];
            }
        }
        $setup = "CREATE TEMPORARY TABLE tuple_sort(n INT,v VARCHAR(2));INSERT INTO tuple_sort VALUES(2,'b'),(1,'a'),(2,'c'),(2,'b');SET @g=0,@x=0;";
        foreach (['@g', '@g:=@g+1', '1+1', 'NOW()', 'NULLIF(@g,0)'] as $key) {
            foreach (['', 'DISTINCT '] as $distinct) {
                yield 'constant sort ' . $distinct . $key => [$setup . 'SELECT GROUP_CONCAT(' . $distinct . 'v ORDER BY ' . $key . ') FROM tuple_sort;SELECT @g'];
            }
        }
        foreach (['GROUP_CONCAT(DISTINCT n)', 'GROUP_CONCAT(DISTINCT n,@x:=@x+1)', 'GROUP_CONCAT(DISTINCT v)', 'GROUP_CONCAT(DISTINCT v,@x:=@x+1)'] as $expression) {
            foreach (['', ' GROUP BY 1+1', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                yield 'distinct tuple ' . $expression . $group => [$setup . 'SELECT ' . $expression . ' FROM tuple_sort' . $group . ';SELECT @x'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateInputsPreserveNullChecksOrderingAndVariableEffects(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
