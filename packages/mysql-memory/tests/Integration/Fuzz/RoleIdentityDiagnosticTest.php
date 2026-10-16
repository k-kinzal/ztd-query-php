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
final class RoleIdentityDiagnosticTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'GRANT `é\' 猫猫\\Z\\0Z猫\'\'\\``猫 ``\\ 猫0\\` TO CURRENT_USER WITH ADMIN OPTION',
 "GRANT 'a`b' TO CURRENT_USER",
 "GRANT 'a`b'@'h`h' TO CURRENT_USER",
 "CREATE USER 'u`r'@'h`h'",
 "SET DEFAULT ROLE 'a`b' TO 'u`r'@'h`h'",
 "SET ROLE 'a`b'",
 "CREATE ROLE 'a`b'",
 "SET ROLE 'a`b'",
 "GRANT 'a`b' TO 'u`r'@'h`h'",
 "GRANT 'a`b' TO 'a`b'",
 "CREATE ROLE 'c`d'",
 "GRANT 'a`b' TO 'c`d'",
 "GRANT 'c`d' TO 'a`b'",
 "SHOW GRANTS FOR 'u`r'@'h`h' USING 'c`d'",
 "CREATE USER 'n`n' DEFAULT ROLE 'm`m'",
 "ALTER USER 'u`r'@'h`h' DEFAULT ROLE 'm`m'",
 "REVOKE 'm`m' FROM CURRENT_USER",
 "REVOKE IF EXISTS 'm`m' FROM CURRENT_USER IGNORE UNKNOWN USER",
 "SHOW GRANTS FOR 'u`r'@'h`h'",
 "SHOW CREATE USER 'u`r'@'h`h'",
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Role lookup, membership, cycles and SQL output, including the complete unseeded regression
     */
    #[DataProvider('providerReleases')]
    public function testRoleDiagnosticsAndSqlOutputUseTheirNativeIdentityForms(string $release, array $statements): void
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
