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
final class WindowExpressionTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['a', 'b'] as $column) {
            yield 'stored source column ' . $column => ["CREATE TABLE window_source(a TINYINT,b CHAR(3)); INSERT INTO window_source VALUES(1,'abc'); SELECT v FROM (SELECT FIRST_VALUE(" . $column . ') OVER () AS v FROM window_source) q'];
        }
        foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 1'] as $tail) {
            foreach (["CAST('[1]' AS JSON)", "COALESCE(FIRST_VALUE(CAST('[1]' AS JSON)) OVER (),JSON_ARRAY(1))"] as $expression) {
                $sql = 'SELECT v FROM (SELECT ' . $expression . ' AS v' . $tail . ') q';
                yield $sql => [$sql];
            }
        }
        $window = 'FIRST_VALUE(NULL) OVER ()';
        foreach (['COALESCE(' . $window . ',1)', 'IFNULL(' . $window . ',1)', 'IF(0,' . $window . ',1)', 'CASE WHEN 1 THEN 1 ELSE ' . $window . ' END'] as $parent) {
            foreach ([$parent, '(' . $parent . ") = '01'"] as $expression) {
                foreach (['', ' FROM DUAL LIMIT 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t WHERE 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0', ' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP', ' FROM (SELECT 1 UNION ALL SELECT 2) t GROUP BY 1+1', ' FROM (SELECT 1 UNION ALL SELECT 2) t GROUP BY 1+1 WITH ROLLUP', ' FROM (SELECT 1 UNION ALL SELECT 2) t GROUP BY 1+1 WITH ROLLUP HAVING 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t GROUP BY 1+1 WITH ROLLUP LIMIT 0'] as $tail) {
                    yield $expression . $tail => ['SELECT ' . $expression . ' AS v' . $tail];
                }
            }
        }
        foreach (['(FIRST_VALUE(10) OVER ())+1', "CONCAT(FIRST_VALUE('abc') OVER (),'x')", "CAST(FIRST_VALUE('abc') OVER () AS CHAR)", "HEX(FIRST_VALUE(CAST('2020-01-02' AS DATE)) OVER ())", 'JSON_ARRAY(FIRST_VALUE(NULL) OVER ())', "COALESCE(FIRST_VALUE(CAST('[1]' AS JSON)) OVER (),JSON_ARRAY(1))", "COALESCE(FIRST_VALUE(CAST('2020-01-02' AS DATE)) OVER (),CAST('2020-01-03' AS DATE))", 'IFNULL(LEAD(NULL,0) OVER (),1)', 'COALESCE(MIN(NULL) OVER (),1)', 'COALESCE(JSON_ARRAYAGG(NULL) OVER (),1)', '(ROW_NUMBER() OVER ())+1'] as $expression) {
            foreach (['', ' FROM DUAL LIMIT 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t WHERE 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0'] as $tail) {
                yield $expression . $tail => ['SELECT ' . $expression . ' AS v' . $tail];
            }
        }
        foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t WHERE 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0'] as $tail) {
            yield 'unrelated constants' . $tail => ["SELECT FIRST_VALUE(10) OVER () AS w,20+1 AS x,'abc' AS s,CAST('2020-01-02' AS DATE) AS d,CAST('[1]' AS JSON) AS j,NULL AS n" . $tail];
        }
        foreach (['NULL', '10', "'abc'", "CAST('[1]' AS JSON)", "CAST('2020-01-02' AS DATE)", "CAST('12:34:56.123' AS TIME(3))", "CAST('2020-01-02 12:34:56.123' AS DATETIME(3))"] as $value) {
            foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 1'] as $from) {
                $query = 'SELECT FIRST_VALUE(' . $value . ') OVER () AS v' . $from;
                foreach (['SELECT (' . $query . ') AS v', 'SELECT COALESCE((' . $query . '),1) AS v', 'SELECT COALESCE((' . $query . "),1) = '01' AS v", 'SELECT v,COALESCE(v,1) AS c,COALESCE(v,1) = \'01\' AS b FROM (' . $query . ') q'] as $sql) {
                    yield $sql => [$sql];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testWindowExpressionsPreserveLogicalTypesAcrossScalarAndTableBoundaries(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
