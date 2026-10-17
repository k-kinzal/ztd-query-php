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
final class WindowResultTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['FIRST_VALUE(10)', 'LAST_VALUE(10)', 'NTH_VALUE(10,1)', 'LAG(10,0)', 'LEAD(10,1,7)', 'MIN(10)'] as $call) {
            foreach (['', ' FROM DUAL', ' FROM DUAL WHERE 0', ' FROM DUAL LIMIT 0', ' FROM DUAL LIMIT 1 OFFSET 1', ' FROM (SELECT 1) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t WHERE 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0'] as $tail) {
                yield $call . $tail => ['SELECT ' . $call . ' OVER () AS v' . $tail];
            }
        }
        foreach (['(FIRST_VALUE(10) OVER ())', '+(FIRST_VALUE(10) OVER ())', '{fn FIRST_VALUE(10) OVER ()}'] as $expression) {
            yield $expression => ['SELECT ' . $expression . ' AS v FROM DUAL'];
        }
        foreach (['NULL', "'abc'", '1.2', '1e0', "CAST('2020-01-02' AS DATE)", "CAST('[1]' AS JSON)", 'CAST(1 AS UNSIGNED)', "X'01'"] as $value) {
            $call = 'FIRST_VALUE(' . $value . ') OVER ()';
            foreach ([$call, '(' . $call . ')', '+(' . $call . ')', '{fn ' . $call . '}'] as $expression) {
                foreach (['', ' FROM DUAL WHERE 0', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0'] as $tail) {
                    yield $expression . $tail => ['SELECT ' . $expression . ' AS v' . $tail];
                }
            }
        }
        foreach (["CAST('[1]' AS JSON)", "CAST('2020-01-02' AS DATE)", '10'] as $value) {
            foreach (['', ' HAVING 0', ' LIMIT 0'] as $tail) {
                $sql = 'SELECT (FIRST_VALUE(' . $value . ') OVER ()) AS v,COUNT(*) AS n FROM (SELECT 1 AS a UNION ALL SELECT 2) t' . $tail;
                yield $sql => [$sql];
            }
            foreach (['', ' FROM (SELECT 1 AS a UNION ALL SELECT 2) t'] as $from) {
                foreach ([' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP', ' GROUP BY 1+1 WITH ROLLUP HAVING 0'] as $tail) {
                    $sql = 'SELECT (FIRST_VALUE(' . $value . ') OVER ()) AS v' . $from . $tail;
                    yield $sql => [$sql];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testWindowFieldsDistinguishDirectAndMaterializedResults(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
