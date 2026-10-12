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
final class TemporaryCommonTableTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerScope(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_order(n INT);INSERT INTO reopen_order VALUES(1);';
        foreach ([
            'WITH reopen_order AS(SELECT 7 n) SELECT a.n,b.n FROM reopen_order a JOIN reopen_order b',
            'WITH reopen_order AS(SELECT 7 n) SELECT a.n,b.n FROM mysql.reopen_order a JOIN reopen_order b',
            'WITH c AS(SELECT n FROM reopen_order) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT n FROM reopen_order i) SELECT a.n,b.n FROM c a JOIN reopen_order b',
            'WITH c AS(SELECT n FROM reopen_order i) SELECT a.n,b.n FROM reopen_order b JOIN c a',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT a.n,b.n FROM c a JOIN reopen_order b',
            'SELECT * FROM reopen_order a JOIN reopen_order b ON (SELECT SUM(n) FROM reopen_order i)>0',
            'SELECT * FROM reopen_order a JOIN (SELECT n FROM reopen_order i) b ON (SELECT SUM(n) FROM reopen_order j)>0',
        ] as $query) {
            yield $query => [$setup . $query];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMaterialized(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_order(n INT);INSERT INTO reopen_order VALUES(1);';
        foreach ([
            'WITH c AS(SELECT n FROM reopen_order i) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT DISTINCT n FROM reopen_order i) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT n FROM reopen_order i GROUP BY n) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT SUM(n) n FROM reopen_order i) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT n,ROW_NUMBER() OVER () r FROM reopen_order i) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT a.n,b.n FROM reopen_order b JOIN c a',
            'WITH unused AS(SELECT n FROM reopen_order i) SELECT * FROM reopen_order o',
            'WITH c AS(SELECT n FROM reopen_order i),d AS(SELECT n FROM c) SELECT * FROM d a JOIN d b',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1),d AS(SELECT n FROM c) SELECT * FROM d a JOIN d b',
        ] as $query) {
            yield $query => [$setup . $query];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPreparation(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_order(n INT);INSERT INTO reopen_order VALUES(1);';
        foreach ([
            'SELECT unknown_column FROM reopen_order a JOIN reopen_order b',
            'SELECT unknown_function(1) FROM reopen_order a JOIN reopen_order b',
            'SELECT SUM(unknown_column) FROM reopen_order a JOIN reopen_order b',
            'SELECT * FROM reopen_order a JOIN reopen_order b JOIN no_such_reopen_table c',
            'SELECT * FROM no_such_reopen_table c JOIN reopen_order a JOIN reopen_order b',
            'WITH c AS(SELECT n FROM reopen_order i) SELECT unknown_column FROM c a JOIN c b',
            'WITH RECURSIVE c(n) AS(SELECT n FROM reopen_order UNION ALL SELECT n+1 FROM c WHERE n<3) SELECT n FROM c',
            'WITH RECURSIVE c(n) AS(SELECT n FROM reopen_order UNION ALL SELECT n+1 FROM c WHERE n<3) SELECT a.n,b.n FROM c a JOIN c b',
            'WITH reopen_order AS(SELECT 7 n) SELECT * FROM (SELECT n FROM reopen_order) a JOIN reopen_order b',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT * FROM c a WHERE FALSE',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT * FROM c a JOIN c b WHERE FALSE',
            'WITH c AS(SELECT n FROM reopen_order i LIMIT 1) SELECT * FROM c a JOIN c b LIMIT 0',
        ] as $query) {
            yield $query => [$setup . $query];
        }
    }

    #[DataProvider('providerScope')]
    #[DataProvider('providerMaterialized')]
    #[DataProvider('providerPreparation')]
    public function testTemporaryCommonTablesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
