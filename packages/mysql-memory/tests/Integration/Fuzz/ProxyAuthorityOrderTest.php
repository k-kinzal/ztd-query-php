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
final class ProxyAuthorityOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE USER u@localhost, v@anotherhost',
            'GRANT PROXY ON missing@remote TO u@localhost, v@anotherhost',
            'REVOKE PROXY ON missing@remote FROM u@localhost, v@anotherhost',
            "GRANT PROXY ON missing@remote TO '" . str_repeat('x', 40) . "'@localhost, v@anotherhost",
            "REVOKE PROXY ON missing@remote FROM '" . str_repeat('x', 40) . "'@localhost, v@anotherhost",
            "GRANT PROXY ON root@'%' TO u@localhost, v@anotherhost",
            'SHOW GRANTS FOR u@localhost',
            "REVOKE PROXY ON root@'%' FROM u@localhost, v@anotherhost",
            'SHOW GRANTS FOR u@localhost',
            'GRANT PROXY ON CURRENT_USER TO CURRENT_USER WITH GRANT OPTION',
            'GRANT PROXY ON missing@remote TO u@localhost, v@anotherhost',
            'SHOW GRANTS FOR v@anotherhost',
            'REVOKE PROXY ON missing@remote FROM u@localhost, v@anotherhost',
            'SHOW GRANTS FOR v@anotherhost',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, str_starts_with($release, '5.') ? $statements : ["REVOKE IF EXISTS PROXY ON _sqlfaker_identifier @GLOBAL FROM _sfOK1KT @localhost , _sqlfaker_identifier @'a''b'", ...$statements]];
        }
    }

    /**
     * @param list<string> $statements Authority changes and the complete unseeded regression
     * The initial root proxy grants match the differential target's explicit fixture.
     */
    #[DataProvider('providerReleases')]
    public function testAuthorityPrecedesRecipientWarningsAndTracksGrantedState(string $release, array $statements): void
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
