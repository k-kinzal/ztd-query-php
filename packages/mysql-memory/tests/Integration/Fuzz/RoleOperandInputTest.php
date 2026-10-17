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
final class RoleOperandInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'CREATE USER u', 'CREATE ROLE r'];
        foreach (['GRANT %s TO %s', 'REVOKE %s FROM %s'] as $form) {
            foreach (['SELECT', 'r, SELECT', 'SELECT, r', 'r(a)', 'SUPER', "'r'@'bad@host', SELECT", "SELECT, 'r'@'bad@host'"] as $roles) {
                foreach (['u', "'u'@'bad@host'", "'" . str_repeat('x', 33) . "'"] as $user) {
                    $statements[] = sprintf($form, $roles, $user);
                }
            }
        }
        array_push(
            $statements,
            'CREATE PROCEDURE d.p() GRANT SELECT TO u',
            'CREATE PROCEDURE d.p() BEGIN DECLARE a INT; DECLARE a INT; GRANT SELECT TO u; END',
            'CREATE PROCEDURE d.p() BEGIN GRANT SELECT TO u; SELECT BINARY 1; END',
            'GRANT SUPER TO u TRAILING',
            'GRANT SELECT TO',
            "GRANT r,\nSELECT TO u",
            'GRANT r, SELECT, UPDATE TO u',
            "REVOKE 'r'@'bad@host', UPDATE, SELECT FROM u",
        );
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$statements, ...($release === '8.0.44' ? [] : ['SHOW PARSE_TREE GRANT SELECT TO u'])]];
        }
    }

    /**
     * @param list<string> $statements The valid roles, rejected operands and competing diagnostics
     */
    #[DataProvider('providerReleases')]
    public function testRoleOperandRefusalsMatchNative(string $release, array $statements): void
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
