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
final class ValueEmptyWindowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['FIRST_VALUE(10)', 'LAST_VALUE(10)', 'NTH_VALUE(10,1)', 'NTH_VALUE(10,2)', 'FIRST_VALUE(NULL)', 'FIRST_VALUE(1/0)', 'FIRST_VALUE(id)', 'LAG(10)', 'LEAD(10)', 'LAG(10,0)', 'LEAD(10,0)', 'LAG(10,1,7)', 'LEAD(10,1,7)', 'LAG(NULL,1,7)', 'LAG(10,1,1/0)'] as $call) {
            foreach (['WHERE 0', 'LIMIT 0', 'LIMIT 1 OFFSET 1'] as $tail) {
                $value = $call . ' OVER ()';
                $columns = [];
                foreach (['plain' => $value, 'plus' => $value . '+0', 'nullcheck' => 'IF(' . $value . ' IS NULL,NULL,1)', 'nonnullcheck' => 'IF(' . $value . ' IS NOT NULL,NULL,1)', 'at10' => 'NULLIF(' . $value . ',10)', 'at7' => 'NULLIF(' . $value . ',7)'] as $name => $expression) {
                    $columns[] = '(SELECT ' . $expression . ',NULL FROM DUAL ' . $tail . ')<=> (NULL,NULL) AS ' . $name;
                }
                yield $call . ' ' . $tail => ['SELECT id,' . implode(',', $columns) . ' FROM (SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3) t ORDER BY id; SHOW WARNINGS'];
            }
        }
        foreach (['FIRST_VALUE(v)', 'LAST_VALUE(v)', 'NTH_VALUE(v,1)', 'NTH_VALUE(v,2)', 'NTH_VALUE(v,3)', 'LAG(v)', 'LAG(v,1,7)', 'LEAD(v,1,7)'] as $call) {
            foreach ([2, 3] as $offset) {
                $value = $call . ' OVER (ORDER BY v)';
                $columns = [];
                foreach (['plain' => $value, 'plus' => $value . '+0', 'nullcheck' => 'IF(' . $value . ' IS NULL,NULL,1)', 'at10' => 'NULLIF(' . $value . ',10)', 'at20' => 'NULLIF(' . $value . ',20)', 'at7' => 'NULLIF(' . $value . ',7)'] as $name => $expression) {
                    $columns[] = '(SELECT ' . $expression . ',NULL FROM (SELECT 10 AS v UNION ALL SELECT 20) s LIMIT 1 OFFSET ' . $offset . ')<=> (NULL,NULL) AS ' . $name;
                }
                yield 'computed ' . $call . ' offset ' . $offset => ['SELECT ' . implode(',', $columns)];
            }
        }
        foreach (['WHERE 1/0', 'HAVING 1/0', 'ORDER BY 1/0'] as $clause) {
            yield 'tableless effects ' . $clause => ['SELECT FIRST_VALUE(1/0) OVER () FROM DUAL ' . $clause . ' LIMIT 1 OFFSET 1; SHOW WARNINGS'];
        }
        yield 'grouped argument' => ['SELECT FIRST_VALUE(SUM(1/0)) OVER () FROM DUAL LIMIT 1 OFFSET 1; SHOW WARNINGS'];
        foreach (['FIRST_VALUE(10) OVER () FROM DUAL', 'FIRST_VALUE(1/0) OVER () FROM DUAL', 'FIRST_VALUE(10) OVER () FROM DUAL WHERE 1/0'] as $expression) {
            yield 'found rows ' . $expression => ['SELECT SQL_CALC_FOUND_ROWS ' . $expression . ' LIMIT 1 OFFSET 1; SELECT FOUND_ROWS(); SHOW WARNINGS'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testNullInspectionAndValueEvaluationAfterAnEmptyWindowMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
