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
final class PrivilegeOperandInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'CREATE USER u'];
        foreach (['GRANT %s ON %s TO %s', 'REVOKE %s ON %s FROM %s'] as $form) {
            foreach (["r@'%'", "SELECT,r@'%'", "r@'bad@host',SELECT", "r@'%',s@'%'", "SUPER,r@'%'"] as $items) {
                foreach (['*.*', 'PROCEDURE *', 'd.*'] as $level) {
                    foreach (['u', "u@'bad@host'"] as $user) {
                        $statements[] = sprintf($form, $items, $level, $user);
                    }
                }
            }
        }
        array_push($statements, "GRANT r@'%' ON *.* TO u TRAILING", "CREATE PROCEDURE d.p() BEGIN GRANT r@'%' ON *.* TO u; SELECT BINARY 1; END", "CREATE PROCEDURE d.p() GRANT r@'%' ON * TO u", 'USE d', "GRANT r@'%' ON PROCEDURE * TO u");
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$statements, ...($release === '8.0.44' ? [] : ["SHOW PARSE_TREE GRANT r@'%' ON *.* TO u"])]];
        }
    }

    /**
     * @param list<string> $statements Rejected privileges, schema dependencies and competing diagnostics
     */
    #[DataProvider('providerReleases')]
    public function testPrivilegeOperandRefusalsMatchNative(string $release, array $statements): void
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
