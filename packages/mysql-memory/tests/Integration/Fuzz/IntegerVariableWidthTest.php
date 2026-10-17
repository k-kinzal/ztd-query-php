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
                yield $charset . ' literal ' . $value => ['SET NAMES ' . $charset . '; SELECT @integer_literal:=' . $value . '; SELECT @integer_literal,@integer_literal+0'];
            }
            foreach (['latin1', 'utf8mb4'] as $results) {
                yield $charset . ' results ' . $results => ['SET NAMES ' . $charset . '; SET @integer_width=0; SET character_set_results=' . $results . '; SELECT @integer_width,@integer_width+0'];
                yield $charset . ' changed ' . $results => ['SET NAMES ' . $charset . '; SET @integer_width=0; SET NAMES ' . $results . '; SELECT @integer_width,@integer_width+0,ABS(@integer_width),ABS(@integer_width)+0,@integer_assigned:=@integer_width; SELECT @integer_assigned'];
            }
            yield $charset . ' absolute column widths' => ['SET NAMES ' . $charset . '; CREATE TABLE absolute_widths(narrow INT(3),wide INT(30)); INSERT INTO absolute_widths VALUES(-12,-12); SELECT narrow,wide,ABS(narrow),ABS(wide),ABS(ABS(narrow)),ABS(ABS(wide)) FROM absolute_widths'];
            yield $charset . ' inherited expressions' => ['SET NAMES ' . $charset . '; SET @integer_width=0; SET NAMES utf8mb4; SELECT @integer_nested:=(@integer_assigned:=@integer_width),ABS(ABS(@integer_width)),@absolute_assigned:=ABS(@integer_width),ABS(@integer_width)+0,ABS(@integer_width)*1,ABS(@integer_width) DIV 1,-ABS(@integer_width); SELECT @integer_nested,@absolute_assigned'];
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
