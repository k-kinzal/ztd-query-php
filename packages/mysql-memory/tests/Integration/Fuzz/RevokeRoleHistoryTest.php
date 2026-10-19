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
final class RevokeRoleHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE USER u, v', 'CREATE ROLE r', (string) file_get_contents(__DIR__ . '/fixtures/revoke-role-recipient.sql')];
        foreach (['', 'IF EXISTS '] as $if) {
            foreach (['', ' IGNORE UNKNOWN USER'] as $ignore) {
                foreach (['missing, r', 'r, missing', 'missing, missing2'] as $roles) {
                    foreach (['u, absent', 'absent, u', 'u, v', 'absent, absent2'] as $users) {
                        $statements = [...$statements, 'GRANT r TO u, v', "REVOKE {$if}$roles FROM $users$ignore", 'SHOW GRANTS FOR u', 'SHOW GRANTS FOR v'];
                    }
                }
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Role and recipient order, partial diagnostics and atomic rollback
     */
    #[DataProvider('providerInputs')]
    public function testRecipientsRetainOrderedWarningsAndRollbackRoleChanges(string $release, array $statements): void
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
