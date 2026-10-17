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
final class IdentifierNoticeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach ([
            'SELECT 1 AS full',
            'SELECT 1 AS `full`',
            "SELECT 1 AS 'full'",
            'SELECT 1 full',
            'SELECT full',
            'SELECT x.full FROM t1',
            'SELECT full.x FROM t1',
            'SELECT !full',
            'SELECT full || 1',
            'SELECT BINARY full',
            'SELECT 1 AS full, BINARY 1',
            'SELECT BINARY 1 AS full',
            "ALTER LOGFILE GROUP FULL ADD UNDOFILE 'text'",
            'SHOW FULL TABLES',
            'SHOW CREATE USER full',
            'SELECT 1 AS full,2 AS FuLl',
            'SELECT SQL_CALC_FOUND_ROWS 1 AS full',
            "SELECT _utf8'test' AS full",
            "SELECT full + _utf8'test'",
            'CREATE TABLE full(a INT(3))',
            'CREATE TABLE full_table(a INT(3),full INT)',
            'CREATE TABLE full_table(full INT,a INT(3))',
            'SELECT BINARY 1 AS full FROM (SELECT BINARY 1) AS x',
            'SELECT 1 AS full FROM (SELECT SQL_CALC_FOUND_ROWS 1) AS x',
            '(SELECT SQL_CALC_FOUND_ROWS 1 AS full)',
            'CREATE TABLE full_table(a CHAR(2) ASCII,full INT)',
            'CREATE TABLE full_table(a CHAR(2) CHARACTER SET utf8,full INT)',
            'CREATE TABLE full_table(full CHAR(2) ASCII, b INT(3))',
            'CREATE PROCEDURE full(IN a INT(3)) SELECT BINARY 1 AS full',
            'SELECT !full || BINARY full',
            'CREATE TABLE full(a BIT(65), full INT(3))',
            'CREATE TABLE full(a INT(3), b BIT(65), full INT)',
        ] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerStatements')]
    public function testIdentifierNoticesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
