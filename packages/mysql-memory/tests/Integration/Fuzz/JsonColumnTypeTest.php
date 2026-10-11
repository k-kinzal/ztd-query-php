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
final class JsonColumnTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $json = "CAST('[1]' AS JSON)";
        $window = 'FIRST_VALUE(' . $json . ') OVER ()';
        $conditional = 'IF(1,' . $window . ',' . $json . ')';
        $expressions = ['COALESCE(' . $json . ',JSON_ARRAY(1))', 'IFNULL(' . $window . ',' . $json . ')', $conditional, 'CASE WHEN 1 THEN ' . $window . ' ELSE ' . $json . ' END', 'LAG(' . $json . ',0) OVER ()', 'LEAD(' . $json . ',0) OVER ()', '+((' . $conditional . '))', '{fn ' . $conditional . '}'];
        foreach (['utf8mb4', 'latin1', 'NULL'] as $charset) {
            $setup = $charset === 'NULL' ? 'SET NAMES utf8mb4; SET character_set_results=NULL; ' : 'SET NAMES ' . $charset . '; ';
            foreach ($expressions as $expression) {
                foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 1'] as $tail) {
                    $query = 'SELECT ' . $expression . ' AS v' . $tail;
                    foreach ([$query, 'SELECT v FROM (' . $query . ') q'] as $sql) {
                        yield $charset . ' ' . $sql => [$setup . $sql];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testJsonColumnsRetainTheReleaseSpecificStorageMetadata(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
