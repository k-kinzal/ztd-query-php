<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RevokeAllHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE USER a,b'];
            foreach (['missing', 'missing,a', 'a,missing', 'missing,a,b', 'a,missing,b', 'a,b,missing', 'missing,missing2', 'a,missing,a'] as $users) {
                array_push($statements, 'GRANT SELECT ON *.* TO a,b', 'REVOKE ALL, GRANT OPTION FROM ' . $users, 'SHOW GRANTS FOR a', 'SHOW GRANTS FOR b');
            }
            if (!str_starts_with($release, '5.')) {
                foreach (['missing', 'missing,a', 'a,missing'] as $users) {
                    array_push($statements, 'GRANT SELECT ON *.* TO a,b', 'REVOKE ALL, GRANT OPTION FROM ' . $users . ' IGNORE UNKNOWN USER', 'SHOW GRANTS FOR a');
                }
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Per-recipient conditions and grant state after failed statements
     * The initial root proxy grants match the differential target's explicit fixture.
     */
    #[DataProvider('providerReleases')]
    public function testRecipientOrderControlsErrorsAndRetainedPrivileges(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $native->exec("DELETE FROM mysql.proxies_priv WHERE User = 'root' AND Host = '%'");
            $native->exec('FLUSH PRIVILEGES');
            $identity = $native->query('SELECT USER()');
            self::assertInstanceOf(PDOStatement::class, $identity);
            $account = $identity->fetchColumn();
            self::assertIsString($account);
            $server = Server::start($release, clientHost: substr($account, (int) strrpos($account, '@') + 1));
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
