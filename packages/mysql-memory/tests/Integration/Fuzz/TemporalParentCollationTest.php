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
final class TemporalParentCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield from self::providerForms();
        $date = "CAST('2020-01-02' AS DATE)";
        $control = 'COALESCE(' . $date . ',' . $date . ')';
        foreach (['latin1', 'utf8mb4'] as $charset) {
            foreach (["_utf8mb4'text'", $date, $control, 'IFNULL(' . $date . ',' . $date . ')', 'IF(1,' . $date . ',' . $date . ')', 'CASE WHEN 1 THEN ' . $date . ' ELSE ' . $date . ' END', 'FIRST_VALUE(' . $date . ') OVER ()', 'FIRST_VALUE(' . $control . ') OVER ()'] as $temporal) {
                foreach (["_latin1'x'", "_utf8mb4'x'", "_binary'x'"] as $literal) {
                    $expression = 'CONCAT(' . $literal . ',' . $temporal . ')';
                    $statements = ['SET NAMES ' . $charset];
                    foreach (['', ' FROM (SELECT 1 UNION ALL SELECT 2) t'] as $tail) {
                        $statements[] = 'SELECT ' . $expression . ' AS v,CHARSET(' . $expression . ') AS cs,COLLATION(' . $expression . ') AS co,COERCIBILITY(' . $expression . ') AS cv' . $tail;
                    }
                    yield $charset . ' ' . $expression => [implode(';', $statements)];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerForms(): iterable
    {
        $date = "CAST('2020-01-02' AS DATE)";
        foreach (['latin1', 'utf8mb4'] as $charset) {
            yield $charset . ' pipes' => ['SET NAMES ' . $charset . "; SET sql_mode='PIPES_AS_CONCAT'; SELECT 'a'||X'41' AS v,CHARSET('a'||X'41') AS cs,COLLATION('a'||X'41') AS co,COERCIBILITY('a'||X'41') AS cv"];
            foreach (["_utf8mb4'text'", $date, 'COALESCE(' . $date . ',' . $date . ')'] as $operand) {
                $expression = "CONCAT_WS(_binary'x'," . $operand . ',' . $operand . ',' . $operand . ')';
                yield $charset . ' ' . $expression => ['SET NAMES ' . $charset . '; SELECT ' . $expression . ' AS v,CHARSET(' . $expression . ') AS cs,COLLATION(' . $expression . ') AS co,COERCIBILITY(' . $expression . ') AS cv'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporalControlsRetainTheirStrengthInParentExpressions(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
