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
final class TemporalInformationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield from self::providerNested();
        foreach (['latin1', 'utf8mb4', 'utf8mb3'] as $charset) {
            foreach (["CAST('2020-01-02' AS DATE)", "CAST('12:34:56.123' AS TIME(3))", "CAST('2020-01-02 12:34:56.123' AS DATETIME(3))"] as $value) {
                foreach ([$value, 'FIRST_VALUE(' . $value . ') OVER ()', 'LAST_VALUE(' . $value . ') OVER ()', 'NTH_VALUE(' . $value . ',1) OVER ()', 'LEAD(' . $value . ',0) OVER ()', 'LAG(' . $value . ',0) OVER ()', 'MIN(' . $value . ') OVER ()', 'MAX(' . $value . ') OVER ()', 'COALESCE(' . $value . ',' . $value . ')', 'IFNULL(' . $value . ',' . $value . ')', 'IF(1,' . $value . ',' . $value . ')', 'CASE WHEN 1 THEN ' . $value . ' ELSE ' . $value . ' END', 'COALESCE(FIRST_VALUE(' . $value . ') OVER (),' . $value . ')'] as $expression) {
                    $statements = ['SET NAMES ' . $charset];
                    foreach (['NULL', 'utf8mb4', 'latin1'] as $results) {
                        $statements[] = 'SET character_set_results=' . $results;
                        foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t'] as $tail) {
                            $statements[] = 'SELECT ' . $expression . ' AS v,CHARSET(' . $expression . ') AS cs,COLLATION(' . $expression . ') AS co,COERCIBILITY(' . $expression . ') AS cv' . $tail;
                        }
                    }
                    yield $charset . ' ' . $expression => [implode(';', $statements)];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNested(): iterable
    {
        $date = "CAST('2020-01-02' AS DATE)";
        $datetime = "CAST('2020-02-03 12:34:56.123' AS DATETIME(3))";
        foreach (['latin1', 'utf8mb4'] as $charset) {
            foreach (['COALESCE(' . $date . ',' . $datetime . ')', 'IFNULL(' . $datetime . ',' . $date . ')', 'IF(1,' . $date . ',' . $datetime . ')', 'CASE WHEN 1 THEN ' . $datetime . ' ELSE ' . $date . ' END'] as $expression) {
                $statements = ['SET NAMES ' . $charset];
                foreach (['NULL', 'utf8mb4'] as $results) {
                    $statements[] = 'SET character_set_results=' . $results;
                    $statements[] = 'SELECT ' . $expression . ' AS v,CHARSET(' . $expression . ') AS cs,COLLATION(' . $expression . ') AS co,COERCIBILITY(' . $expression . ') AS cv';
                }
                yield $charset . ' mixed date datetime ' . $expression => [implode(';', $statements)];
            }
        }
        $value = "COALESCE(CAST('2020-01-02' AS DATE),CAST('2020-01-03' AS DATE))";
        foreach (['latin1', 'utf8mb4', 'utf8mb3'] as $charset) {
            foreach (['FIRST_VALUE(' . $value . ') OVER ()', 'NTH_VALUE(' . $value . ',1) OVER ()', 'LEAD(' . $value . ',0) OVER ()', 'MIN(' . $value . ') OVER ()', 'MIN(' . $value . ')', '(SELECT ' . $value . ')'] as $expression) {
                $statements = ['SET NAMES ' . $charset];
                foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t'] as $tail) {
                    $statements[] = 'SELECT CHARSET(' . $expression . ') AS cs,COLLATION(' . $expression . ') AS co,COERCIBILITY(' . $expression . ') AS cv' . $tail;
                }
                yield $charset . ' nested ' . $expression => [implode(';', $statements)];
            }
            yield $charset . ' stored control' => ['SET NAMES ' . $charset . ';SELECT CHARSET(v),COLLATION(v),COERCIBILITY(v) FROM (SELECT ' . $value . ' AS v UNION ALL SELECT ' . $value . ') t'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporalInformationPreservesLogicalAndStoredCollations(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
