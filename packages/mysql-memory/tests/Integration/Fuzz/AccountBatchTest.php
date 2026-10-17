<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AccountBatchTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testRecipientOrderRollbackAndLaterSessionAccessMatchMySql(string $release): void
    {
        $statements = ["CREATE USER existing IDENTIFIED BY 'old'",
"ALTER USER IF EXISTS absent IDENTIFIED WITH absent_plugin BY 'x', CURRENT_USER IDENTIFIED BY 'x' REPLACE 'wrong'",
"ALTER USER absent IDENTIFIED WITH absent_plugin BY 'x', existing IDENTIFIED WITH missing_plugin BY 'x'",
'ALTER USER CURRENT_USER DROP 3 FACTOR, CURRENT_USER, CURRENT_USER DROP 3 FACTOR',
'SELECT 1',
"SET PASSWORD='root'",
"ALTER USER CURRENT_USER DROP 3 FACTOR, CURRENT_USER IDENTIFIED BY 'root', CURRENT_USER DROP 3 FACTOR",
'SELECT 1',
"SET PASSWORD='root'",
'ALTER USER CURRENT_USER DROP 3 FACTOR, CURRENT_USER',
'SELECT 1',
"SET PASSWORD='root'",
'ALTER USER CURRENT_USER, CURRENT_USER DROP 3 FACTOR',
'SELECT 1',
"SET PASSWORD='root'",
'ALTER USER CURRENT_USER',
'SELECT 1',
"SET PASSWORD='root'",
"CREATE USER existing AND IDENTIFIED WITH absent_plugin AND IDENTIFIED BY 'x'",
"CREATE USER IF NOT EXISTS existing AND IDENTIFIED BY 'x' AND IDENTIFIED BY 'y'",
"CREATE USER existing AND IDENTIFIED BY 'x' AND IDENTIFIED BY 'y'",
"CREATE USER fresh AND IDENTIFIED BY 'x', fresh2 AND IDENTIFIED BY 'y'",
"CREATE USER fresh AND IDENTIFIED WITH caching_sha2_password AND IDENTIFIED BY 'y'",
"CREATE USER fresh AND IDENTIFIED BY 'x' AND IDENTIFIED WITH caching_sha2_password",
"ALTER USER CURRENT_USER IDENTIFIED BY 'root', CURRENT_USER IDENTIFIED BY 'new' REPLACE 'root'",
"SET PASSWORD='root'",
"ALTER USER CURRENT_USER IDENTIFIED BY 'changed', CURRENT_USER IDENTIFIED BY 'new' REPLACE 'root'",
"SET PASSWORD='root'",
'SHOW GRANTS FOR existing'];
        array_unshift($statements, "SET PASSWORD='root'", "ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD REPLACE 'wrong', CURRENT_USER IDENTIFIED WITH absent_plugin BY 'x' REPLACE 'wrong', missing IDENTIFIED WITH SESSION BY RANDOM PASSWORD RETAIN CURRENT PASSWORD");
        $statements[] = "ALTER USER absent IDENTIFIED WITH absent_plugin BY 'x', CURRENT_USER IDENTIFIED BY 'new'";
        $statements[] = 'SELECT 1';
        $statements[] = "SET PASSWORD='root'";
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
