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
final class InformationNullabilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['', 'STRICT_TRANS_TABLES', 'PIPES_AS_CONCAT', 'STRICT_ALL_TABLES'] as $mode) {
            foreach (["'x'", 'NULL', "CONCAT('a',X'41')", '1', "CAST('2020-01-02' AS DATE)"] as $argument) {
                yield $mode . ' ' . $argument => ["SET NAMES utf8mb4; SET sql_mode='" . $mode . "'; SELECT CHARSET(" . $argument . ') AS cs,COLLATION(' . $argument . ') AS co'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testInformationFunctionNullabilityFollowsTheSqlMode(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
