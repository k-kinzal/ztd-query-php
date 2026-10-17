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
final class TimestampInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d'];
        foreach (['bad', '2024-01-02', '2024-13-01 00:00:00', '2024-01-02 24:00:00', '2024X01X02 03:04:05', '2024-01-02 03:04:05-00:00', '2024-01-02 03:04:05+14:01', '2024010203040'] as $text) {
            $literal = "TIMESTAMP '" . $text . "'";
            array_push($statements, 'SELECT ' . $literal, 'SELECT ' . $literal . ', BINARY 1', 'SELECT BINARY 1, ' . $literal, 'SELECT ' . $literal . ' TRAILING GARBAGE', 'CREATE PROCEDURE d.p() SELECT ' . $literal, "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT=" . $literal);
        }
        foreach (['2024-01-02 03:04:05', '20240102030405', '2401020', '2024-01-02 03:04:05.12+05:30'] as $text) {
            $statements[] = "SELECT TIMESTAMP '" . $text . "'";
        }
        foreach (['', 'NO_ZERO_DATE,NO_ZERO_IN_DATE'] as $mode) {
            $statements[] = "SET sql_mode='" . $mode . "'";
            array_push($statements, "SELECT TIMESTAMP '2024-00-01 03:04:05+00:00'", "SELECT TIMESTAMP '0000-00-00 00:00:00+01:00'");
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_TIMESTAMP_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
            }
            yield $release => [$release, $definition, $statements];
        }
    }

    /**
     * @param list<string> $statements Accepted and refused TIMESTAMP representations with competing input conditions
     */
    #[DataProvider('providerInputs')]
    public function testTimestampRepresentationsMatchNative(string $release, DisposableMySql $definition, array $statements): void
    {
        $container = Testcontainers::run($definition);
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
