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
final class ConstantGroupOutputTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TEMPORARY TABLE constant_group_output(n INT,s VARCHAR(10),d DATE);INSERT INTO constant_group_output VALUES(1,'a','2001-01-02'),(2,'b','2001-01-03');SET @g=2,@x=0;";
        foreach (['MIN(s)', 'CONCAT(MIN(s))', 'GROUP_CONCAT(s)', 'JSON_ARRAYAGG(n>1)', 'JSON_OBJECTAGG(n,n>1)', 'MIN(MIN(d)) OVER ()', '@x:=MIN(MIN(d)) OVER ()', 'CONCAT(MIN(MIN(d)) OVER ())', '@x:=MIN(d)', 'CONCAT(MIN(d))'] as $expression) {
            foreach (['', ' GROUP BY 1+1', " GROUP BY CONCAT('a','b')", ' GROUP BY @g', ' GROUP BY 1+1 WITH ROLLUP', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'sibling ' . $expression . $group . $distinct => [$setup . 'SELECT ' . $distinct . $expression . ' AS v,ROW_NUMBER() OVER () AS w FROM constant_group_output' . $group . ';SELECT @x,CHARSET(@x)'];
                }
            }
        }
        foreach (['@x:=MIN(MIN(d)) OVER ()', 'CONCAT(MIN(MIN(d)) OVER ())', '@x:=MIN(d)', 'CONCAT(MIN(d))', '@x:=MIN(MIN(s)) OVER ()'] as $expression) {
            foreach (['', ' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'isolated ' . $expression . $group . $distinct => [$setup . 'SELECT ' . $distinct . $expression . ' AS v FROM constant_group_output' . $group . ';SELECT @x,CHARSET(@x)'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testConstantAndVaryingGroupsPreserveResultStorageAndAssignments(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
