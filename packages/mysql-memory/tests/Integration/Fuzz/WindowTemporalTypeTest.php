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
final class WindowTemporalTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['utf8mb4', 'latin1', 'utf8mb3'] as $charset) {
            foreach (["CAST('2020-01-02' AS DATE)", "CAST('12:34:56.123' AS TIME(3))", "CAST('2020-01-02 12:34:56.123' AS DATETIME(3))"] as $value) {
                foreach (['FIRST_VALUE(' . $value . ')', 'LAST_VALUE(' . $value . ')', 'NTH_VALUE(' . $value . ',1)', 'LAG(' . $value . ',0)', 'LEAD(' . $value . ',0)', 'MIN(' . $value . ')', 'MAX(' . $value . ')'] as $call) {
                    $statements = ['SET NAMES ' . $charset];
                    foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t', ' FROM (SELECT 1 UNION ALL SELECT 2) t LIMIT 0'] as $tail) {
                        $statements[] = 'SELECT ' . $call . ' OVER () AS v' . $tail;
                    }
                    $statements[] = 'SHOW WARNINGS';
                    yield $charset . ' ' . $call => [implode(';', $statements)];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporalWindowTypesFollowTheirExecutionPathAndResultCharset(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
