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
final class UnionIntegerWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TABLE integer_union(a BIGINT(4),b BIGINT UNSIGNED,c INT);INSERT INTO integer_union VALUES(1,2,3),(4,5,6);SET @x=1;';
        foreach (['a', 'b', 'a+c', 'ABS(a)', '@x', '@x:=@x+c', '@x:=a', 'CAST(c AS SIGNED)', 'CAST(c AS UNSIGNED)', '10000000000'] as $expression) {
            foreach (['', 'ALL '] as $duplicates) {
                foreach (['', ' ORDER BY v'] as $order) {
                    yield $expression . ' ' . $duplicates . $order => [$setup . 'SELECT ' . $expression . ' AS v FROM integer_union UNION ' . $duplicates . 'SELECT ' . $expression . ' FROM integer_union' . $order];
                }
            }
        }
        foreach (['@x:=@x+c', '@x:=a'] as $expression) {
            foreach (['', 'ALL '] as $duplicates) {
                foreach ([false, true] as $first) {
                    $value = 'SELECT ' . $expression . ' AS v FROM integer_union';
                    $null = 'SELECT NULL AS v';
                    yield $expression . ' ' . $duplicates . ($first ? 'leading null' : 'trailing null') => [$setup . ($first ? $null : $value) . ' UNION ' . $duplicates . ($first ? $value : $null)];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testIntegerFieldsKeepTheirExpressionAndStorageBounds(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
