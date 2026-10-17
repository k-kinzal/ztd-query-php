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
final class OrderingAliasScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE alias_order(n INT);INSERT INTO alias_order VALUES(1),(2),(2),(NULL);';
        foreach (['', 'DISTINCT '] as $distinct) {
            foreach (['SUM(n)', 'MIN(n)', 'ABS(n)', 'n'] as $field) {
                foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                    foreach (['x', 'x+1', 'ABS(x)', '(SELECT x)', '(SELECT ABS(x))', '(SELECT (SELECT x))', '(SELECT n)', '(SELECT SUM(n))', '(SELECT SUM(i.n) FROM alias_order i)'] as $order) {
                        yield $distinct . $field . $group . $order => [$setup . 'SELECT ' . $distinct . $field . ' AS x FROM alias_order' . $group . ' ORDER BY ' . $order];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testOrderingAliasScopesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
