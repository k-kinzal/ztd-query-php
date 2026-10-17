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
final class AggregateEmptyWindowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield from self::providerInitial();
        yield from self::providerTables();
        yield from self::providerReentry();
        yield from self::providerAverages();
        yield from self::providerOffsets();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInitial(): iterable
    {
        foreach (['COUNT(*)', 'COUNT(NULL)', 'SUM(10)', 'AVG(10)', 'MIN(10)', 'MAX(10)', 'BIT_AND(10)', 'BIT_OR(10)', 'BIT_XOR(10)', 'VAR_SAMP(10)', 'JSON_ARRAYAGG(10)', "JSON_OBJECTAGG('k',10)", 'JSON_OBJECTAGG(NULL,10)', 'SUM(1/0)'] as $call) {
            foreach (['', 'ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING'] as $window) {
                foreach (['FROM DUAL WHERE 0', 'FROM DUAL LIMIT 0', 'FROM (SELECT 1 UNION ALL SELECT 2) w WHERE 0', 'FROM (SELECT 1 UNION ALL SELECT 2) w LIMIT 0'] as $tail) {
                    $expression = $call . ' OVER (' . $window . ')';
                    $columns = [];
                    foreach (['plain' => $expression, 'plus' => $expression . '+0', 'isnull' => 'IF(' . $expression . ' IS NULL,NULL,1)', 'notnull' => 'IF(' . $expression . ' IS NOT NULL,NULL,1)', 'zero' => 'NULLIF(' . $expression . ',0)', 'ten' => 'NULLIF(' . $expression . ',10)', 'twenty' => 'NULLIF(' . $expression . ',20)'] as $name => $value) {
                        $columns[] = '(SELECT ' . $value . ',NULL ' . $tail . ') <=> (NULL,NULL) AS ' . $name;
                    }
                    yield $call . ' ' . $window . ' ' . $tail => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT " . implode(',', $columns) . ' FROM (SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3) t ORDER BY id; SHOW WARNINGS'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerTables(): iterable
    {
        foreach (['COUNT(*)', 'SUM(a)', 'AVG(a)', 'MIN(a)', 'MAX(a)', 'BIT_XOR(a)', 'SUM(10)', 'AVG(10)', 'VAR_SAMP(10)'] as $call) {
            foreach (['WHERE a<0', 'WHERE 0', 'LIMIT 0', 'LIMIT 1 OFFSET 2', 'WHERE t.id=2 LIMIT 1 OFFSET 2'] as $tail) {
                $expression = $call . ' OVER ()';
                $columns = [];
                foreach (['plain' => $expression, 'plus' => $expression . '+0', 'isnull' => 'IF(' . $expression . ' IS NULL,NULL,1)'] as $name => $value) {
                    $columns[] = '(SELECT ' . $value . ',NULL FROM window_aggregate_source ' . $tail . ') <=> (NULL,NULL) AS ' . $name;
                }
                foreach ([0, 1, 2, 10, 15, 20, 30, 40, 60] as $value) {
                    $columns[] = '(SELECT NULLIF(' . $expression . ',' . $value . '),NULL FROM window_aggregate_source ' . $tail . ') <=> (NULL,NULL) AS at' . $value;
                }
                yield 'source ' . $call . ' ' . $tail => ['CREATE TABLE window_aggregate_source(a INT); INSERT INTO window_aggregate_source VALUES(10),(20); SELECT id,' . implode(',', $columns) . ' FROM (SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3) t ORDER BY id'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReentry(): iterable
    {
        foreach (['COUNT(*)', 'SUM(10)', 'BIT_XOR(10)', 'VAR_SAMP(10)'] as $call) {
            foreach (['FROM DUAL WHERE t.id=2 LIMIT 1 OFFSET 1', 'FROM (SELECT 1 UNION ALL SELECT 2) w WHERE t.id=2 LIMIT 1 OFFSET 2', 'FROM (SELECT 1 UNION ALL SELECT 2) w WHERE t.id<>2 LIMIT 1 OFFSET 2'] as $tail) {
                $expression = $call . ' OVER ()';
                $columns = ['(SELECT ' . $expression . ',NULL ' . $tail . ') <=> (NULL,NULL) AS n'];
                foreach ([0, 1, 2, 3, 4, 10, 20, 30, 40] as $value) {
                    $columns[] = '(SELECT NULLIF(' . $expression . ',' . $value . '),NULL ' . $tail . ') <=> (NULL,NULL) AS at' . $value;
                }
                yield 'reentry ' . $call . ' ' . $tail => ['SELECT id,' . implode(',', $columns) . ' FROM (SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) t ORDER BY id'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerAverages(): iterable
    {
        foreach (['', ' FROM DUAL', ' FROM (SELECT 1) w', ' FROM (SELECT 1 UNION ALL SELECT 2) w', ' FROM (SELECT 1) w JOIN (SELECT 1) z ON 1'] as $from) {
            foreach ([' WHERE 0', ' LIMIT 0', ' LIMIT 1 OFFSET 2'] as $tail) {
                $columns = [];
                foreach ([10, 20, 30] as $value) {
                    $columns[] = '(SELECT NULLIF(AVG(10) OVER (),' . $value . '),NULL' . $from . $tail . ') <=> (NULL,NULL) AS at' . $value;
                }
                yield 'average ' . $from . $tail => ['SELECT ' . implode(',', $columns) . ' FROM (SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3) t ORDER BY id'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerOffsets(): iterable
    {
        foreach ([' FROM DUAL', ' FROM (SELECT 1) w', ' FROM (SELECT 1) w JOIN (SELECT 1) z ON 1', ' FROM (SELECT 1 UNION ALL SELECT 2) w'] as $from) {
            foreach (['FIRST_VALUE(1/0) OVER ()', 'SUM(1/0) OVER ()'] as $expression) {
                yield 'counted offset ' . $expression . $from => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT SQL_CALC_FOUND_ROWS " . $expression . $from . ' LIMIT 1 OFFSET 2; SELECT FOUND_ROWS(); SHOW WARNINGS'];
            }
        }
        foreach ([' FROM DUAL', ' FROM (SELECT 1) w'] as $from) {
            foreach ([' GROUP BY 1+1', ' GROUP BY 1+1 WITH ROLLUP'] as $group) {
                foreach ([1, 2] as $offset) {
                    yield 'grouped offset ' . $from . $group . $offset => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT FIRST_VALUE(1/0) OVER ()" . $from . $group . ' LIMIT 1 OFFSET ' . $offset . '; SHOW WARNINGS'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testInitialAndRetainedAggregateWindowStateMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
