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
final class TemporalAggregateTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TEMPORARY TABLE temporal_aggregate_type(d DATE,t TIME(3),dt DATETIME(3),n INT); INSERT INTO temporal_aggregate_type VALUES ('2020-01-02','12:34:56.123','2020-01-02 12:34:56.123',1),('2020-01-03','13:34:56.123','2020-01-03 12:34:56.123',2); ";
        foreach (['d', 't', 'dt', 'COALESCE(d,d)', "CAST('2020-01-02' AS DATE)"] as $argument) {
            foreach (['MIN', 'MAX'] as $function) {
                foreach (['', ' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $tail) {
                    $value = $function . '(' . $argument . ')';
                    yield $value . $tail => [$setup . 'SELECT ' . $value . ' AS v,CHARSET(' . $value . ') AS cs,COLLATION(' . $value . ') AS co,COERCIBILITY(' . $value . ') AS cv FROM temporal_aggregate_type' . $tail];
                }
            }
        }
        foreach (['MIN(d)', 'COALESCE(MIN(d),MIN(d))', 'MIN(d)+INTERVAL 1 DAY', 'CAST(MIN(d) AS DATE)', 'MIN(MIN(d)) OVER ()'] as $value) {
            foreach ([' GROUP BY n WITH ROLLUP', ' GROUP BY n WITH ROLLUP ORDER BY v', ' GROUP BY n WITH ROLLUP HAVING MIN(d) IS NOT NULL', ' GROUP BY n WITH ROLLUP LIMIT 2', ' GROUP BY n ORDER BY v'] as $tail) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'shape ' . $distinct . $value . $tail => [$setup . 'SELECT ' . $distinct . $value . ' AS v,CHARSET(' . $value . ') AS cs,COLLATION(' . $value . ') AS co,COERCIBILITY(' . $value . ') AS cv FROM temporal_aggregate_type' . $tail];
                }
            }
        }
        foreach (['MIN(d)' => '', 'MIN(MIN(d))' => ' GROUP BY n'] as $argument => $group) {
            foreach (['()', 'w'] as $over) {
                $value = $argument . ' OVER ' . $over;
                $fields = [[$value], ['CHARSET(' . $value . ')'], ['COLLATION(' . $value . ')'], ['CHARSET(' . $value . ')', 'COLLATION(' . $value . ')'], ['COLLATION(' . $value . ')', 'CHARSET(' . $value . ')'], [$value, 'CHARSET(' . $value . ')', 'COLLATION(' . $value . ')'], [$value, 'CHARSET(' . $value . ')', 'COLLATION(' . $value . ')', 'COERCIBILITY(' . $value . ')']];
                foreach ($fields as $index => $projection) {
                    yield 'window phase ' . $value . ' ' . $index => [$setup . 'SELECT ' . implode(',', $projection) . ' FROM temporal_aggregate_type' . $group . ($over === 'w' ? ' WINDOW w AS ()' : '')];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporalAggregateFieldsAndIntrospectionMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
