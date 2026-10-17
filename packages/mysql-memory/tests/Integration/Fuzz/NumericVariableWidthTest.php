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
final class NumericVariableWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['ascii', 'latin1', 'utf8mb4'] as $charset) {
            foreach (['1.5', '-1.50', '0.0', '1e0', 'CAST(NULL AS DECIMAL(5,2))', 'NULLIF(1e0,1e0)'] as $value) {
                yield $charset . ' literal ' . $value => ['SET NAMES ' . $charset . ';SELECT @numeric_literal:=' . $value . ';SELECT @numeric_literal,@numeric_literal+0,ABS(@numeric_literal)'];
                foreach (['', 'SET NAMES latin1;', 'SET NAMES utf8mb4;'] as $changed) {
                    yield $charset . ' ' . $value . ' ' . $changed => ['SET NAMES ' . $charset . ';SET @numeric_width=' . $value . ';' . $changed . 'SELECT @numeric_width,@numeric_width+0,ABS(@numeric_width),CHARSET(@numeric_width),@numeric_copy:=@numeric_width;SELECT @numeric_copy'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testNumericVariableWidthsAndExpressionsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
