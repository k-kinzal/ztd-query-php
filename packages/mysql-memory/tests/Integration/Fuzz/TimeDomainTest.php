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
final class TimeDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d'];
        foreach (['bad', '+1', '839:00:00', '838:59:59.1', '000000000001', '1:60:00', '- 1'] as $text) {
            $literal = "TIME '" . $text . "'";
            array_push($statements, 'SELECT ' . $literal, 'SELECT ' . $literal . ', BINARY 1', 'SELECT BINARY 1, ' . $literal, 'SELECT ' . $literal . ' TRAILING GARBAGE', 'CREATE PROCEDURE d.p() SELECT ' . $literal, "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT=" . $literal);
        }
        foreach (['-0', '1.5', '838:59:58.9999999', '00000000001', '000000000000:00:01', "1\t01:02:03", "1\n01:02:03", "1\r01:02:03", "1\v01:02:03", "1\f01:02:03", '- 00:00:01'] as $text) {
            $statements[] = "SELECT TIME '" . $text . "'";
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Accepted and refused TIME representations with competing input conditions
     */
    #[DataProvider('providerInputs')]
    public function testTimeRepresentationsMatchNative(string $release, array $statements): void
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
