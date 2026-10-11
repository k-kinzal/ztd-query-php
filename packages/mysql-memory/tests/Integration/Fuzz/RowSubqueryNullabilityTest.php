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
final class RowSubqueryNullabilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $comparisons = static function (string $query): string {
            $columns = [];
            foreach (['(1,2)', '(NULL,NULL)', '(SELECT 1,2)', '(SELECT NULL,NULL)'] as $index => $operand) {
                $columns[] = '(' . $query . ') = ' . $operand . ' AS comparison_' . $index;
                $columns[] = $operand . ' = (' . $query . ') AS reversed_' . $index;
            }

            return 'SELECT ' . implode(',', $columns);
        };
        foreach (['nonnull' => '1,2', 'partial' => '1,NULL', 'null' => 'NULL,NULL'] as $fields => $projection) {
            foreach (['present' => '', 'limited' => ' LIMIT 0', 'filtered' => ' FROM DUAL WHERE 0', 'true filter' => ' FROM DUAL WHERE 1'] as $name => $tail) {
                yield $fields . ' ' . $name => [$comparisons('SELECT ' . $projection . $tail)];
            }
            yield $fields . ' empty union' => [$comparisons('SELECT ' . $projection . ' FROM DUAL WHERE 0 UNION ALL SELECT ' . $projection . ' FROM DUAL WHERE 0')];
            yield $fields . ' table union' => [$comparisons('SELECT ' . $projection . ' FROM t1 WHERE 0 UNION ALL SELECT ' . $projection . ' FROM t2 WHERE 0')];
        }
        yield 'table limit' => [$comparisons('SELECT id,a FROM t1 LIMIT 0')];
        yield 'aggregate having' => [$comparisons('SELECT COUNT(*),COUNT(*) FROM t1 HAVING 0')];
        yield 'aggregate true filter' => [$comparisons('SELECT COUNT(*),COUNT(*) FROM DUAL WHERE 1')];
        yield 'aggregate false filter' => [$comparisons('SELECT COUNT(*),COUNT(*) FROM DUAL WHERE 0')];
        yield 'scalar aggregate true filter' => ['SELECT (SELECT COUNT(*) FROM DUAL WHERE 1) = 1 AS comparison'];
        yield 'scalar aggregate false filter' => ['SELECT (SELECT COUNT(*) FROM DUAL WHERE 0) = 1 AS comparison'];
    }

    #[DataProvider('providerStatements')]
    public function testRowSubqueryNullabilityMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
