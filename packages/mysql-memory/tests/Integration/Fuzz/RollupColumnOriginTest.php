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
final class RollupColumnOriginTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {

        foreach (['INT', 'INT NOT NULL', 'INT PRIMARY KEY'] as $type) {
            foreach (['', 'INSERT INTO rollup_columns VALUES(1),(2);'] as $rows) {
                foreach (['', ' WHERE FALSE', ' WHERE n<0', ' WHERE n=1'] as $where) {
                    foreach (['n', 'n,COUNT(*)', 'n,SUM(n)', 'n,GROUP_CONCAT(n)'] as $fields) {
                        yield $type . $rows . $where . $fields => ['CREATE TEMPORARY TABLE rollup_columns(n ' . $type . ');' . $rows . 'SELECT ' . $fields . ' FROM rollup_columns' . $where . ' GROUP BY n WITH ROLLUP'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testAggregateOutputPhasesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
