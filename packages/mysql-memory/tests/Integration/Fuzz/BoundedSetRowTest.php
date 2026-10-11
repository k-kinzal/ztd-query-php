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
final class BoundedSetRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $branches = [
            'literal' => 'SELECT 1,NULL',
            'true condition' => 'SELECT 1,NULL FROM DUAL WHERE 1=1',
            'false condition' => 'SELECT 1,NULL FROM DUAL WHERE 1=0',
            'having' => 'SELECT 1,NULL FROM DUAL HAVING 1',
            'aggregate' => 'SELECT COUNT(*),NULL',
            'derived' => 'SELECT a,NULL FROM (SELECT 1 a) q',
            'bounded operand' => '(SELECT 1,NULL LIMIT 1)',
            'empty operand' => '(SELECT 1,NULL LIMIT 0)',
            'null fields' => 'SELECT NULL,NULL',
            'runtime true' => 'SELECT 1,NULL FROM DUAL WHERE RAND()<2',
            'runtime false' => 'SELECT 1,NULL FROM DUAL WHERE RAND()<0',
            'session condition' => 'SELECT 1,NULL FROM DUAL WHERE @@autocommit',
        ];
        foreach ($branches as $name => $branch) {
            foreach (['same' => $branch, 'literal' => 'SELECT 2,NULL'] as $other => $right) {
                foreach (['zero' => 'LIMIT 0', 'offset' => 'LIMIT 1 OFFSET 2'] as $bound => $limit) {
                    $query = $branch . ' UNION ALL ' . $right . ' ' . $limit;
                    yield $name . ' ' . $other . ' ' . $bound => ['SELECT (' . $query . ') <=> (0,NULL) AS value_pair, (' . $query . ') <=> (NULL,NULL) AS null_pair, (' . $query . ') <=> (' . $query . ') AS self_pair'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptySetAvailabilityMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
