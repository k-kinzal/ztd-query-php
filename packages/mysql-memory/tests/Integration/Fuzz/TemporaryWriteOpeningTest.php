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
final class TemporaryWriteOpeningTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_write(n INT PRIMARY KEY);INSERT INTO reopen_write VALUES(1),(2);';
        foreach ([
            'INSERT INTO reopen_write SELECT n+2 FROM reopen_write',
            'INSERT INTO reopen_write SELECT i.n+2 FROM reopen_write i',
            'REPLACE INTO reopen_write SELECT i.n FROM reopen_write i',
            'UPDATE reopen_write SET n=n+10',
            'UPDATE reopen_write a JOIN reopen_write b SET a.n=b.n WHERE FALSE',
            'UPDATE reopen_write SET n=(SELECT MAX(i.n) FROM reopen_write i)',
            'DELETE FROM reopen_write WHERE n IN(SELECT i.n FROM reopen_write i)',
            'DELETE a FROM reopen_write a JOIN reopen_write b',
            'INSERT INTO reopen_write WITH c AS(SELECT n FROM reopen_write i) SELECT n+2 FROM c',
            'WITH c AS(SELECT n FROM reopen_write i) UPDATE reopen_write a JOIN c b SET a.n=b.n WHERE FALSE',
            'WITH c AS(SELECT n FROM reopen_write i) DELETE a FROM reopen_write a JOIN c b',
            'CREATE TEMPORARY TABLE reopen_target AS SELECT * FROM reopen_write a JOIN reopen_write b USING(n)',
        ] as $query) {
            yield $query => [$setup . $query . ';SELECT * FROM reopen_write ORDER BY n'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporaryOpeningMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
