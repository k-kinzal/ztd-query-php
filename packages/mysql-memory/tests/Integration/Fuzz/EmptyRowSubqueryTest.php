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
final class EmptyRowSubqueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $queries = [
            'literal filter' => 'SELECT 1,2 WHERE 0',
            'null filter' => 'SELECT NULL,NULL WHERE 0',
            'column filter' => 'SELECT id,a FROM t1 WHERE 0',
            'table literal filter' => 'SELECT 1,2 FROM t1 WHERE 0',
            'literal limit' => 'SELECT 1,2 LIMIT 0',
            'column limit' => 'SELECT id,a FROM t1 LIMIT 0',
            'literal offset' => 'SELECT 1,2 LIMIT 1 OFFSET 1',
            'column offset' => 'SELECT id,a FROM t1 LIMIT 1 OFFSET 10',
            'missing key' => 'SELECT id,a FROM t1 WHERE id=99',
            'empty scan' => 'SELECT id,a FROM t1 WHERE a=999',
            'having' => 'SELECT MIN(id),MAX(a) FROM t1 HAVING 0',
            'union' => 'SELECT 1,2 WHERE 0 UNION ALL SELECT 1,2 WHERE 0',
            'null limit' => 'SELECT NULL,NULL LIMIT 0',
            'partial null filter' => 'SELECT NULL,1 WHERE 0',
            'computed null filter' => 'SELECT NULL+1,CAST(NULL AS SIGNED) WHERE 0',
            'literal missing key' => 'SELECT 1,2 FROM t1 WHERE id=99',
            'null table filter' => 'SELECT NULL,NULL FROM t1 WHERE 0',
            'null scan' => 'SELECT NULL,NULL FROM t1 WHERE a=999',
            'null union' => 'SELECT NULL,NULL WHERE 0 UNION ALL SELECT NULL,NULL WHERE 0',
            'nullable column filter' => 'SELECT a,b FROM t1 WHERE 0',
            'nullable missing key' => 'SELECT a,b FROM t1 WHERE id=99',
            'count having' => 'SELECT COUNT(*),COUNT(id) FROM t1 HAVING 0',
            'mixed aggregate having' => 'SELECT COUNT(*),MAX(id) FROM t1 HAVING 0',
            'literal having' => 'SELECT 1,2 HAVING 0',
            'null having' => 'SELECT NULL,NULL HAVING 0',
        ];
        foreach ($queries as $name => $left) {
            $comparisons = [];
            foreach (array_slice(array_values($queries), 0, 12) as $index => $right) {
                $comparisons[] = '(' . $left . ') <=> (' . $right . ') AS safe_' . $index;
                $comparisons[] = '(' . $left . ') = (' . $right . ') AS plain_' . $index;
            }
            $comparisons[] = '(' . $left . ') <=> (SELECT NULL,NULL) AS null_row';
            $comparisons[] = '(NULL,NULL) <=> (' . $left . ') AS null_constructor';
            $comparisons[] = '(1,2) <=> (' . $left . ') AS literal_constructor';
            $comparisons[] = '(0,NULL) <=> (' . $left . ') AS zero_null_constructor';
            $comparisons[] = '(0,0) <=> (' . $left . ') AS zero_constructor';
            $comparisons[] = '(' . $left . ') <=> (' . $left . ') AS self_comparison';
            $comparisons[] = '(1,NULL) <=> (' . $left . ') AS literal_null_constructor';
            yield $name => ['SELECT ' . implode(',', $comparisons)];
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyRowsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
