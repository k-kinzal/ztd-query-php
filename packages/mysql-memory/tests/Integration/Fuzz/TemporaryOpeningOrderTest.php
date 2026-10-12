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
final class TemporaryOpeningOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_order(n INT);INSERT INTO reopen_order VALUES(1);';
        foreach ([
            'SELECT * FROM reopen_order a JOIN reopen_order b',
            'SELECT (SELECT SUM(i.n) FROM reopen_order i) FROM reopen_order o',
            'SELECT o.n FROM reopen_order o WHERE EXISTS(SELECT 1 FROM reopen_order i)',
            'SELECT o.n FROM reopen_order o ORDER BY (SELECT SUM(i.n) FROM reopen_order i)',
            'SELECT o.n FROM reopen_order o GROUP BY o.n HAVING (SELECT SUM(i.n) FROM reopen_order i)>0',
            'SELECT n FROM reopen_order a UNION ALL SELECT n FROM reopen_order b',
            'SELECT * FROM (SELECT n FROM reopen_order i) d JOIN reopen_order o',
            'SELECT * FROM reopen_order o JOIN (SELECT n FROM reopen_order i) d',
            'SELECT (SELECT SUM(i.n) FROM reopen_order i) FROM reopen_order o ORDER BY (SELECT SUM(j.n) FROM reopen_order j)',
            '(SELECT n FROM reopen_order a UNION ALL SELECT n FROM reopen_order b) ORDER BY (SELECT SUM(i.n) FROM reopen_order i)',
        ] as $query) {
            yield $query => [$setup . $query];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporaryOpeningOrderMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
