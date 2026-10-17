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
final class AggregateStringFieldTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TEMPORARY TABLE aggregate_string_field(d DATE,n INT,s VARCHAR(10)); INSERT INTO aggregate_string_field VALUES('2020-01-02',1,'a'),('2020-01-03',2,'b'); ";
        foreach (['CHARSET(MIN(d))', 'CONCAT(MIN(n))', 'CONCAT(MIN(s))', 'CAST(MIN(n) AS CHAR)', 'MIN(s)', 'CHARSET(MIN(MIN(d)) OVER ())', 'CONCAT(MIN(MIN(n)) OVER ())', 'CONCAT(MIN(MIN(s)) OVER ())', 'CAST(MIN(MIN(n)) OVER () AS CHAR)', 'MIN(MIN(s)) OVER ()', 'CHARSET(MIN(d) OVER ())', 'CONCAT(MIN(n) OVER ())'] as $value) {
            foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield $distinct . $value . $group => [$setup . 'SELECT ' . $distinct . $value . ' AS v FROM aggregate_string_field' . $group];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateStringMetadataMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
