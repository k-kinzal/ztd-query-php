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
final class RoutineListingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE listing_conditions',
            'USE listing_conditions',
            'SHOW PROCEDURE STATUS WHERE (((USER())))',
            'SHOW FUNCTION STATUS WHERE 0',
            'SHOW PROCEDURE STATUS WHERE 1 = 0',
            'SHOW FUNCTION STATUS WHERE NULL',
            "SHOW PROCEDURE STATUS WHERE Db = 'absent_database' AND Name = 'absent_routine'",
            'SET NAMES latin1',
            'SHOW PROCEDURE STATUS WHERE (((USER())))',
            'SHOW FUNCTION STATUS WHERE 0',
            'SET NAMES utf8mb4',
            "LOAD DATA INFILE 'text' INTO TABLE absent_table ROWS IDENTIFIED BY 0x0f",
            "LOAD XML INFILE 'text' INTO TABLE absent_table ROWS IDENTIFIED BY X'0f'",
            "LOAD XML INFILE 'text' INTO TABLE absent_table ROWS IDENTIFIED BY B'1'",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The metadata and literal history to compare
     */
    #[DataProvider('providerReleases')]
    public function testConstantEmptyRoutineListingsAndLoadTagsMatchNative(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $identity = $native->query("SELECT SUBSTRING_INDEX(USER(), '@', -1)");
            self::assertNotFalse($identity);
            $server = Server::start($release, clientHost: (string) $identity->fetchColumn());
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
