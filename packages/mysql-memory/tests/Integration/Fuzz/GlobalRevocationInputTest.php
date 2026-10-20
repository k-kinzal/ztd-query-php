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
final class GlobalRevocationInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t(a INT)',
            "REVOKE ALL PRIVILEGES ON *.* FROM a@'named.host',b@'other.host'",
            "REVOKE ALL PRIVILEGES ON *.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON *.* FROM a@'named.host',b@'other.host'",
            "REVOKE SELECT ON *.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON d.* FROM a@'named.host',b@'other.host'",
            "REVOKE SELECT ON d.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON d.t FROM a@'named.host',b@'other.host'",
            "REVOKE SELECT ON d.t FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "CREATE USER a@'named.host'",
            "GRANT ALL ON *.* TO a@'named.host'",
            "REVOKE ALL PRIVILEGES ON *.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON *.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON d.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            "REVOKE SELECT ON d.t FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER",
            'REVOKE ALL PRIVILEGES ON *.* FROM missing',
            'REVOKE ALL PRIVILEGES ON *.* FROM missing, missing2',
            'REVOKE IF EXISTS ALL PRIVILEGES ON *.* FROM missing',
            "GRANT SELECT ON d.t TO a@'named.host'",
            "REVOKE ALL PRIVILEGES ON *.* FROM a@'named.host'",
            "SHOW GRANTS FOR a@'named.host'",
            "REVOKE ALL PRIVILEGES ON *.* FROM LANGUAGE @'\r~z?\nV\r''sW0\x06' , GLOBAL @DO IGNORE UNKNOWN USER",
            'REVOKE ALL ON TABLE *.* FROM missing',
            'REVOKE ALL ON TABLE *.* FROM missing IGNORE UNKNOWN USER',
            'REVOKE ALL ON PROCEDURE *.* FROM missing',
            'REVOKE ALL ON PROCEDURE *.* FROM missing IGNORE UNKNOWN USER',
            'REVOKE ALL ON FUNCTION *.* FROM missing',
            'REVOKE ALL ON FUNCTION *.* FROM missing IGNORE UNKNOWN USER',
            "GRANT SELECT ON d.t TO a@'named.host'",
            "REVOKE ALL ON *.* FROM a@'named.host',missing",
            "SHOW GRANTS FOR a@'named.host'",
            "REVOKE ALL ON *.* FROM missing,a@'named.host'",
            "SHOW GRANTS FOR a@'named.host'",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Global and narrower revocations across missing and existing recipients
     */
    #[DataProvider('providerStatements')]
    public function testGlobalRevocationRetainsRecipientOrderAndAtomicChanges(string $release, array $statements): void
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
