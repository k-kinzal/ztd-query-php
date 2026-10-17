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
final class UnionFoundRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TABLE union_count(n INT);INSERT INTO union_count VALUES(1),(2),(2),(3);SET @x=0;';
        $operands = [
            'constants' => ['1 AS v', '2 AS v'],
            'tables' => ['n AS v FROM union_count WHERE n<=2', 'n AS v FROM union_count WHERE n>=2'],
            'empty' => ['n AS v FROM union_count WHERE 0', 'n AS v FROM union_count WHERE 0'],
            'assignments' => ['@x:=@x+n AS v FROM union_count WHERE n<=2', '@x:=@x+n AS v FROM union_count WHERE n>=2'],
            'nested assignments' => ['@x:=@x+n AS v FROM union_count WHERE n<=2 UNION ALL SELECT @x:=@x+n FROM union_count WHERE n=2', '@x:=@x+n AS v FROM union_count WHERE n=3'],
            'nested distinct assignments' => ['@x:=@x+n AS v FROM union_count WHERE n<=2 UNION SELECT @x:=@x+n FROM union_count WHERE n=2', '@x:=@x+n AS v FROM union_count WHERE n=3'],
        ];
        foreach ($operands as $name => [$left, $right]) {
            foreach (['', 'ALL '] as $duplicates) {
                foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                    foreach (['', ' LIMIT 0', ' LIMIT 1', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 20', ' ORDER BY v DESC LIMIT 1 OFFSET 1', ' ORDER BY v DESC LIMIT 0'] as $tail) {
                        yield $name . ' ' . $duplicates . $calculate . $tail => [$setup . 'SELECT ' . $calculate . $left . ' UNION ' . $duplicates . 'SELECT ' . $right . $tail . ';SELECT FOUND_ROWS(),@x'];
                    }
                }
            }
        }
        foreach ([
            'distinct then all' => 'SELECT @x:=@x+1 AS v UNION SELECT @x:=@x+1 UNION ALL SELECT @x:=@x+1',
            'all then distinct' => 'SELECT @x:=@x+1 AS v UNION ALL SELECT @x:=@x+1 UNION SELECT @x:=@x+1',
            'parenthesized distinct' => '(SELECT @x:=@x+1 AS v UNION SELECT @x:=@x+1) UNION ALL SELECT @x:=@x+1',
            'global distinct chain' => 'SELECT @x:=@x+1 AS v UNION SELECT @x:=@x+1 UNION ALL SELECT @x:=@x+1 UNION SELECT @x:=@x+1',
            'converted operand' => 'SELECT @x:=@x+1 AS v UNION ALL SELECT CAST(@x:=@x+1 AS DECIMAL(5,1)) UNION ALL SELECT @x:=@x+1',
        ] as $name => $query) {
            yield $name => ['SET @x=0;' . $query . ' LIMIT 1;SELECT @x'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testSetOperationsPreserveCountsAndOperandEffects(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
