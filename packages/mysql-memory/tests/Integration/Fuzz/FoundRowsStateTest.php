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
final class FoundRowsStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TABLE found_state(n INT);INSERT INTO found_state VALUES(1),(2),(3);';
        foreach ([0, 3] as $previous) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach (['1 AS v', 'n AS v FROM found_state', '1 AS v UNION ALL SELECT 2', '1 AS v UNION SELECT 2'] as $body) {
                    foreach ([' LIMIT 0', ' LIMIT 1 OFFSET 9', ' ORDER BY v LIMIT 0'] as $bounds) {
                        yield $previous . ' ' . $calculate . $body . $bounds => [$setup . 'SELECT n FROM found_state WHERE n<=' . $previous . ';SELECT ' . $calculate . $body . $bounds . ';SELECT FOUND_ROWS()'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testBoundsUpdateThePreviousStatementRowCountLikeMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
