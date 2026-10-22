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
final class GroupCredentialStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "START GROUP_REPLICATION PASSWORD = 'p'",
            "START GROUP_REPLICATION USER = '', PASSWORD = 'p'",
            "START GROUP_REPLICATION USER = 'u', USER = ''",
            "START GROUP_REPLICATION PASSWORD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'",
            'CREATE DATABASE group_domain',
            'USE group_domain',
            "CREATE PROCEDURE p() START GROUP_REPLICATION PASSWORD = 'p'",
            'CALL p()',
            "CREATE PROCEDURE ignored() BEGIN IF 0 THEN START GROUP_REPLICATION PASSWORD = 'p'; END IF; END",
            'CALL ignored()',
            "CREATE PROCEDURE caught() BEGIN DECLARE CONTINUE HANDLER FOR 3971 SET @caught = 1; START GROUP_REPLICATION PASSWORD = 'p'; END",
            'CALL caught()',
            'SELECT @caught',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Explicit credential combinations and stored invocation conditions
     */
    #[DataProvider('providerReleases')]
    public function testGroupCredentialsPreserveStoredInvocationConditions(string $release, array $statements): void
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
