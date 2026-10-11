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
final class BranchPrecisionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['10', '-1', '123', '1e0'] as $value) {
            foreach ([$value, 'CASE WHEN 0 THEN 0 ELSE ' . $value . ' END', 'IF(0,0,' . $value . ')', 'COALESCE(' . $value . ',0)', 'IFNULL(NULL,' . $value . ')', 'CASE WHEN 0 THEN NULL ELSE IF(0,0,' . $value . ') END', 'IF(0,NULL,CASE WHEN 0 THEN 0 ELSE ' . $value . ' END)'] as $expression) {
                yield $expression => ['SELECT ' . $expression . ' AS v,(' . $expression . ')+0 AS a,(' . $expression . ')*1 AS m,CAST(' . $expression . ' AS CHAR) AS c,CAST(' . $expression . ' AS BINARY) AS b FROM t2'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testBranchPrecisionSurvivesArithmeticAndTextConversion(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
