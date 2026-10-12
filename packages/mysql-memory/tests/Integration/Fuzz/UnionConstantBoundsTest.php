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
final class UnionConstantBoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TABLE constant_union(n INT);INSERT INTO constant_union VALUES(3),(1),(2);SET @x=0;';
        foreach ([
            'constants' => ['@x:=@x+1 AS v', '@x:=@x+1 AS v'],
            'constant before table' => ['@x:=@x+1 AS v', '@x:=@x+n AS v FROM constant_union'],
            'table before constant' => ['@x:=@x+n AS v FROM constant_union', '@x:=@x+1 AS v'],
            'dual' => ['@x:=@x+1 AS v FROM DUAL', '@x:=@x+1 AS v FROM DUAL'],
            'filtered constants' => ['@x:=@x+1 AS v WHERE 1', '@x:=@x+1 AS v WHERE 0'],
            'derived constants' => ['@x:=@x+n AS v FROM (SELECT 1 AS n) AS d', '@x:=@x+n AS v FROM (SELECT 2 AS n) AS d'],
        ] as $name => [$left, $right]) {
            foreach (['', 'ALL '] as $duplicates) {
                foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                    foreach ([' LIMIT 0', ' LIMIT 1', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 9'] as $bounds) {
                        yield $name . ' ' . $duplicates . $calculate . $bounds => [$setup . 'SELECT ' . $calculate . $left . ' UNION ' . $duplicates . 'SELECT ' . $right . $bounds . ';SELECT FOUND_ROWS(),@x'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testMixedConstantAndTableInputsPreserveCountsAndProjectionEffects(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
