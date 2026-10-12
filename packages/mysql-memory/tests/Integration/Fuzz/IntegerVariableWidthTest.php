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
final class IntegerVariableWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['ascii', 'latin1', 'utf8mb4'] as $charset) {
            foreach (['0', '-1', 'CAST(18446744073709551615 AS UNSIGNED)'] as $value) {
                yield $charset . ' ' . $value => ['SET NAMES ' . $charset . '; SET @integer_width=' . $value . '; SELECT @integer_width,CHARSET(@integer_width),COLLATION(@integer_width),@integer_width+0,ABS(@integer_width),LENGTH(@integer_width),@integer_assigned:=@integer_width,(@integer_assigned:=@integer_width)+0'];
            }
            foreach (['latin1', 'utf8mb4'] as $results) {
                yield $charset . ' results ' . $results => ['SET NAMES ' . $charset . '; SET @integer_width=0; SET character_set_results=' . $results . '; SELECT @integer_width,@integer_width+0'];
                yield $charset . ' changed ' . $results => ['SET NAMES ' . $charset . '; SET @integer_width=0; SET NAMES ' . $results . '; SELECT @integer_width,@integer_width+0'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testVariableMetadataAndArithmeticMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
