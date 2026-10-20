<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SetEncodingInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
         'CREATE DATABASE d','USE d',
         "SET NAMES `3`, PASSWORD = OLD_PASSWORD('1'), @`PRIMARY` = 'あ' COLLATE ABCDE",
         "SET NAMES missing_charset, @v = 'a' COLLATE missing_collation",
         "SET @v = 'a' COLLATE missing_collation, NAMES missing_charset",
         "SET CHARACTER SET missing_charset, @v = 'a' COLLATE missing_collation",
         "SET @v = 'a' COLLATE missing_collation, CHARACTER SET missing_charset",
         "SET NAMES latin1 COLLATE missing_collation, @v = 'a' COLLATE other_collation",
         "SET NAMES latin1 COLLATE utf8mb4_bin, @v = 'a' COLLATE missing_collation",
         "SET NAMES ucs2, @v = 'a' COLLATE missing_collation",
         "SET CHARACTER SET ucs2, @v = 'a' COLLATE missing_collation",
         "SET NAMES missing_charset, @v = BINARY 'a'",
         "SET @v = BINARY 'a', NAMES missing_charset",
         "SET NAMES latin1 COLLATE missing_collation, @v = BINARY 'a'",
         "SET NAMES DEFAULT COLLATE missing_collation, @v = BINARY 'a'",
         'SET NAMES missing_charset COLLATE missing_collation',
         'SET NAMES missing_charset, no_such_variable=1',
         'SET no_such_variable=1, NAMES missing_charset',
         'CREATE PROCEDURE p() BEGIN IF FALSE THEN SET NAMES missing_charset; END IF; END',
         'CREATE PROCEDURE q() BEGIN IF FALSE THEN SET CHARACTER SET missing_charset; END IF; END',
         'CREATE PROCEDURE r() BEGIN IF FALSE THEN SET NAMES latin1 COLLATE utf8mb4_bin; END IF; END',
         'CREATE PROCEDURE s() BEGIN IF FALSE THEN SET NAMES ucs2; END IF; END',
         "SET NAMES utf8, @v = BINARY 'a'",
         "SET CHARACTER SET utf8, @v = BINARY 'a'",

         "SET @v = 'a' COLLATE missing_collation, NAMES ucs2",
         "SET @v = 'a' COLLATE missing_collation, CHARACTER SET ucs2",
         'SET NAMES ucs2, @v = missing_column',
         'SET @v = missing_column, NAMES ucs2',
         'SET NAMES ucs2, NAMES latin1 COLLATE utf8mb4_bin',
         'SET NAMES missing_charset COLLATE',
         'SET CHARACTER SET missing_charset, @v=',
         'SET NAMES latin1 COLLATE missing_collation, @v=',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Encoding name resolution, malformed continuations and ordered value preparation
     */
    #[DataProvider('providerStatements')]
    public function testEncodingNamesAndAssignmentsRetainTheirNativePhases(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
