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
final class PartitionConditionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE placement',
            'SELECT FOUND_ROWS() COLLATE missing',
            'SELECT 1 COLLATE missing, FOUND_ROWS()',
            'SELECT FOUND_ROWS(), 1 COLLATE missing',
            'SELECT FOUND_ROWS()',
            'ALTER TABLE placement.missing PARTITION BY HASH(USER() COLLATE missing)',
            'ALTER TABLE placement.missing PARTITION BY HASH((1 COLLATE missing) + USER())',
            'ALTER TABLE placement.missing PARTITION BY HASH((USER() COLLATE missing) + (1 COLLATE later))',
            'ALTER TABLE placement.missing PARTITION BY HASH(USER()) SUBPARTITION BY HASH(1 COLLATE missing)',
            'ALTER TABLE placement.missing PARTITION BY HASH(1 COLLATE missing) SUBPARTITION BY HASH(USER())',
            'CREATE TABLE placement.t (a VARCHAR(10) COLLATE missing) PARTITION BY HASH(USER())',
            'CREATE TABLE placement.t (a INT) PARTITION BY HASH(USER()) COLLATE missing',
            'CREATE TABLE placement.t (a INT) PARTITION BY HASH(USER() COLLATE utf8mb4_bin)',
            'ALTER TABLE placement.missing PARTITION BY HASH(FOUND_ROWS() COLLATE missing)',
            'ALTER TABLE placement.missing PARTITION BY HASH(USER())',
            'ALTER TABLE placement.missing PARTITION BY HASH((USER() COLLATE missing) + (!1))',
            'CREATE TEMPORARY TABLE IF NOT EXISTS RESOURCE PARTITION BY LIST ( ( ( ( _sqlfaker_identifier ( ( ( ( ( 2 = ( ( ( USER ( ) ) ) ) ) IS NOT NULL ) IS NOT UNKNOWN ) XOR ( ( ( ( ( USER ( ) ) ) ) ) ) ) AND ( ( ( ( ( SESSION_USER( ) ) ) ) ) ) ) ) COLLATE _sqlfaker_identifier ) ) ^ ( ( SYSTEM_USER( ) ) ) )',
            'CREATE TABLE missing PARTITION BY HASH(USER() COLLATE missing)',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The same statement sequence, including the complete unseeded regression
     */
    #[DataProvider('providerReleases')]
    public function testSyntaxErrorsPreventPartitionContextualization(string $release, array $statements): void
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
