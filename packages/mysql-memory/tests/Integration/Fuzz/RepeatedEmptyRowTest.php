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
final class RepeatedEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['NULL', '0', '1', '1+0', 'SQRT(1)', 'SQRT(-1)', '1/0', 'NULL+1', 'CONCAT(NULL)', "CONCAT('x')", "CAST('x' AS SIGNED)", 'IFNULL(NULL,1)', "STR_TO_DATE('x','%Y')"] as $expression) {
            yield $expression => ['SELECT id, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (0,NULL) AS z, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (1,NULL) AS one FROM t2 ORDER BY id'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testRepeatedEmptyProjectionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
