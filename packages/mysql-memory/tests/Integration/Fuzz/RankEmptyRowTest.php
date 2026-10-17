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
final class RankEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['ROW_NUMBER()', 'RANK()', 'DENSE_RANK()', 'PERCENT_RANK()', 'CUME_DIST()', 'NTILE(2)'] as $function) {
            foreach (['WHERE 0', 'LIMIT 0', 'LIMIT 1 OFFSET 5', 'LIMIT 1 OFFSET 6'] as $tail) {
                $value = $function . ' OVER (ORDER BY id)';
                $columns = ['(SELECT ' . $value . ',NULL FROM t1 ' . $tail . ') <=> (NULL,NULL) AS n'];
                foreach ([0, 1, 2, 4, 5, 6] as $number) {
                    $columns[] = '(SELECT NULLIF(' . $value . ',' . $number . '),NULL FROM t1 ' . $tail . ') <=> (NULL,NULL) AS v' . $number;
                }
                yield $function . ' ' . $tail => ['SELECT ' . implode(',', $columns)];
            }
        }
        yield 'two initial windows' => ['SELECT (SELECT NULLIF(ROW_NUMBER() OVER (ORDER BY id),1),NULLIF(PERCENT_RANK() OVER (ORDER BY a),0) FROM t1 WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'two completed windows' => ['SELECT (SELECT NULLIF(ROW_NUMBER() OVER (ORDER BY id),5),NULLIF(DENSE_RANK() OVER (ORDER BY id),5) FROM t1 LIMIT 1 OFFSET 5) <=> (NULL,NULL) AS n'];
        yield 'nested null tests' => ['SELECT (SELECT ISNULL(ROW_NUMBER() OVER ()),PERCENT_RANK() OVER () IS NOT NULL FROM t1 WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'repeated initial read' => ['SELECT id,(SELECT NULLIF(RANK() OVER (ORDER BY id),1),NULL FROM t1 WHERE 0) <=> (NULL,NULL) AS n FROM t2 ORDER BY id'];
        yield 'rank followed by a value window' => ['SELECT (SELECT ROW_NUMBER() OVER (),FIRST_VALUE(a) OVER () FROM t1 WHERE 0) <=> (NULL,NULL) AS n'];
    }

    #[DataProvider('providerStatements')]
    public function testRankStateAfterAnEmptyResultMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
