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
final class KeyPrefixDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
  'CREATE TABLE t(a TEXT, KEY(a(0)))', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a TEXT)',
  'CREATE TABLE IF NOT EXISTS t(a TEXT, KEY(a(0)))', 'ALTER TABLE no_such_table ADD KEY(a(0))', 'CREATE INDEX k ON no_such_table(a(0))',
  'CREATE TABLE u(a TEXT, KEY(a(000)))', 'CREATE TABLE u(a TEXT, KEY(a(0)), b INT)',
  'CREATE TABLE u(a TEXT, KEY(a(0)), b)', 'CREATE TABLE u(a TEXT, KEY(a(0)), b VARCHAR(4) CHARACTER SET utf8)',
  'CREATE TABLE u(a VARCHAR(4) COLLATE missing_collation, KEY(a(0)))', 'CREATE TABLE u(a TEXT, KEY(a(0)), b VARCHAR(4) COLLATE missing_collation)',
  'CREATE PROCEDURE p() CREATE TABLE u(a TEXT, KEY(a(0)))', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN CREATE TABLE u(a TEXT, KEY(a(0))); END IF; END',
  'CREATE TABLE u(a TEXT, KEY(`a``b`(0)))', 'CREATE TABLE u(a TEXT, FOREIGN KEY(a(0)) REFERENCES t(a))',
  'CREATE TABLE u(a TEXT, KEY(a(0) DESC))', 'CREATE TABLE u(a TEXT, KEY(a(0) BOGUS))', 'CREATE TABLE u(a TEXT, KEY(a(0) ASC), ?)',
        ];
        foreach (['INT', 'CHAR(1)', 'VARCHAR(1)', 'BINARY(1)', 'VARBINARY(1)', 'TEXT', 'BLOB', "ENUM('a','b')", "SET('a','b')", 'DATE', 'BIT(8)'] as $type) {
            foreach (['KEY', 'UNIQUE KEY'] as $kind) {
                array_push($statements, 'CREATE TABLE bounded(a ' . $type . ', ' . $kind . '(a(2)))', 'DROP TABLE IF EXISTS bounded');
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Prefix-domain refusals, condition order and skipped definition branches
     */
    #[DataProvider('providerStatements')]
    public function testPrefixDomainPrecedesTableStateAndRetainsLaterConditions(string $release, array $statements): void
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
