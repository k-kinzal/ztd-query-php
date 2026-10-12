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
final class FoundRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE found_rows_input(n INT,d DATE); INSERT INTO found_rows_input VALUES(1,'2001-01-02'),(2,'2001-01-03'),(3,'2001-01-04'); SET @x=0; ";
        foreach (['n', 'n+1', 'COUNT(*)', 'MIN(MIN(d)) OVER ()', 'ROW_NUMBER() OVER ()', '@x:=ROW_NUMBER() OVER ()', '@x:=FIRST_VALUE(d) OVER ()'] as $value) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach ([' LIMIT 0', ' LIMIT 1', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 5', ' ORDER BY v DESC LIMIT 1 OFFSET 1', ' ORDER BY v DESC LIMIT 1 OFFSET 5'] as $tail) {
                    yield $value . $calculate . $tail => [$setup . 'SELECT ' . $calculate . $value . ' AS v FROM found_rows_input' . $tail . '; SELECT @x,CHARSET(@x),FOUND_ROWS()'];
                }
            }
        }
        foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
            foreach ([' LIMIT 0', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 5', ' ORDER BY v DESC LIMIT 1 OFFSET 1'] as $tail) {
                yield 'union ' . $calculate . $tail => ['SELECT ' . $calculate . '1 AS v UNION ALL SELECT 2' . $tail . '; SELECT FOUND_ROWS()'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testConsumedAndReturnedRowCountsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
