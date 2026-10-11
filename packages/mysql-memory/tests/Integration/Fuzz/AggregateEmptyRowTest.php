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
final class AggregateEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $queries = [
            'extrema having' => 'SELECT MIN(id),MAX(a) FROM t1 HAVING 0',
            'count extreme having' => 'SELECT COUNT(*),MAX(id) FROM t1 HAVING 0',
            'extreme count having' => 'SELECT MAX(id),COUNT(*) FROM t1 HAVING 0',
            'counts having' => 'SELECT COUNT(*),COUNT(id) FROM t1 HAVING 0',
            'extrema limit' => 'SELECT MIN(id),MAX(a) FROM t1 LIMIT 0',
            'extrema offset' => 'SELECT MIN(id),MAX(a) FROM t1 LIMIT 0 OFFSET 2',
            'unread division' => 'SELECT SUM(1/0),MIN(1/0) FROM t1 HAVING 0',
            'unread conversion' => "SELECT SUM(CAST('x' AS SIGNED)),MAX(a) FROM t1 HAVING 0",
            'aggregate expressions' => 'SELECT COUNT(*)+1,MAX(id)+1 FROM t1 HAVING 0',
            'null count' => 'SELECT NULLIF(COUNT(*),0),MIN(id) FROM t1 HAVING 0',
            'runtime having' => 'SELECT MIN(id),MAX(a) FROM t1 HAVING COUNT(*)>99',
            'empty input having' => 'SELECT MIN(id),MAX(a) FROM t1 WHERE a=999 HAVING COUNT(*)>0',
            'empty count having' => 'SELECT COUNT(*),MIN(id) FROM t1 WHERE a=999 HAVING COUNT(*)>0',
            'statistical having' => 'SELECT STDDEV_POP(a),VARIANCE(a) FROM t1 HAVING 0',
            'concatenation having' => 'SELECT GROUP_CONCAT(b),SUM(a) FROM t1 HAVING 0',
            'json having' => 'SELECT JSON_ARRAYAGG(id),JSON_OBJECTAGG(id,b) FROM t1 HAVING 0',
        ];
        foreach ($queries as $name => $query) {
            yield $name => ['SELECT (' . $query . ') <=> (NULL,NULL) AS nulls, (' . $query . ') <=> (0,NULL) AS zero_null, (' . $query . ') <=> (99,NULL) AS nonzero_null, (' . $query . ') <=> (NULL,0) AS null_zero, (' . $query . ') = (NULL,NULL) AS plain'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyAggregateProjectionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
