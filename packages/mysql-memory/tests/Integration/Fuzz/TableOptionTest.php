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
final class TableOptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE TABLE t(a INT) COLLATE missing PARTITION BY HASH(RAND())',
            'CREATE TABLE t(a INT) CHARACTER SET missing PARTITION BY HASH(RAND())',
            'CREATE TABLE t(a TEXT CHARACTER SET missing)',
            'CREATE TABLE t(a TEXT COLLATE missing)',
            'SELECT CAST(1 AS CHAR CHARACTER SET missing)',
            'SELECT CONVERT(1 USING missing)',
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t(a INT) COLLATE missing PARTITION BY HASH(RAND())',
            'CREATE TABLE t(a INT) CHARACTER SET missing PARTITION BY HASH(RAND())',
            'CREATE TABLE t(a INT) COLLATE missing CHARACTER SET other',
            'CREATE TABLE t(a INT) CHARACTER SET missing COLLATE other',
            'CREATE TABLE t(a INT) COLLATE missing AS SELECT BINARY 1',
            'ALTER TABLE t COLLATE missing PARTITION BY HASH(RAND())',
            'ALTER TABLE t CHARACTER SET missing, ADD COLUMN a INT',
            'CREATE TABLE t(a TEXT CHARACTER SET missing)',
            'CREATE TABLE t(a TEXT COLLATE missing)',
            'CREATE TABLE t(a TEXT BINARY CHARACTER SET missing)',
            'CREATE TABLE t(a TEXT CHARACTER SET missing BINARY)',
            'CREATE TABLE t(a TEXT COLLATE missing AS (\'x\'))',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The definition and database-selection histories to compare
     */
    #[DataProvider('providerReleases')]
    public function testOptionOrderPrecedesFollowingClauses(string $release, array $statements): void
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
