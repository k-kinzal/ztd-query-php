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
final class DirectWindowEvaluationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield from self::providerFrames();
        yield from self::providerReads();
        yield from self::providerFound();
        yield from self::providerPhysical();
        foreach ([' FROM DUAL', ' FROM (SELECT 1 AS a) t', ' FROM (SELECT 1 AS a UNION ALL SELECT 2) t'] as $from) {
            foreach (['ROW_NUMBER() OVER ()', 'FIRST_VALUE(GROUPING(1+1)) OVER ()', 'LAST_VALUE(GROUPING(1+1)) OVER ()', 'SUM(GROUPING(1+1)) OVER ()', 'COUNT(*) OVER ()', 'FIRST_VALUE(1/0) OVER ()', 'SUM(1/0) OVER ()'] as $expression) {
                foreach (['', ' LIMIT 1', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 2'] as $tail) {
                    $sql = 'SELECT ' . $expression . ' AS v' . $from . ' GROUP BY 1+1 WITH ROLLUP' . $tail;
                    yield $sql => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO';" . $sql . ';SHOW WARNINGS'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerFrames(): iterable
    {
        foreach ([' FROM DUAL', ' FROM (SELECT 1 AS a) t'] as $from) {
            foreach (['', ' GROUP BY 1+1 WITH ROLLUP'] as $group) {
                foreach (['', 'ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING', 'ROWS BETWEEN CURRENT ROW AND CURRENT ROW'] as $window) {
                    foreach (['SUM(10)', 'AVG(10)', 'FIRST_VALUE(10)', 'COUNT(*)', 'ROW_NUMBER()'] as $function) {
                        $sql = 'SELECT ' . $function . ' OVER (' . $window . ') AS v' . $from . $group;
                        yield 'frame ' . $sql => [$sql];
                    }
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReads(): iterable
    {
        foreach (['', 'ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', 'ORDER BY a ROWS BETWEEN CURRENT ROW AND CURRENT ROW', 'ORDER BY a ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING', 'ORDER BY a ROWS BETWEEN 2 FOLLOWING AND 2 FOLLOWING'] as $window) {
            foreach (['FIRST_VALUE(1/0)', 'LAST_VALUE(1/0)', 'NTH_VALUE(1/0,1)', 'NTH_VALUE(1/0,2)'] as $function) {
                $sql = 'SELECT ' . $function . ' OVER (' . $window . ') AS v FROM (SELECT 1 AS a UNION ALL SELECT 2 UNION ALL SELECT 3) t';
                yield $sql => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO';" . $sql . ';SHOW WARNINGS'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerFound(): iterable
    {
        foreach ([' FROM DUAL', ' FROM (SELECT 1 AS a) t'] as $from) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach (['LIMIT 0', 'LIMIT 1', 'LIMIT 1 OFFSET 1', 'LIMIT 1 OFFSET 2'] as $limit) {
                    $sql = 'SELECT ' . $calculate . 'FIRST_VALUE(1/0) OVER () AS v' . $from . ' GROUP BY 1+1 WITH ROLLUP ' . $limit;
                    yield 'found ' . $sql => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO';" . $sql . ';SHOW WARNINGS;SELECT FOUND_ROWS()'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPhysical(): iterable
    {
        foreach (['id=1', 'id=99', 'id IN(1,2)'] as $where) {
            $from = ' FROM t1 WHERE ' . $where . ' GROUP BY a WITH ROLLUP LIMIT 1 OFFSET 1';
            yield 'physical values ' . $where => ['SELECT AVG(10) OVER (),COUNT(*) OVER (),FIRST_VALUE(GROUPING(a)) OVER ()' . $from];
            yield 'physical warnings ' . $where => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO';SELECT FIRST_VALUE(1/0) OVER ()" . $from . ';SHOW WARNINGS'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testDirectAndBufferedWindowsPreserveEvaluationAndBounds(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
