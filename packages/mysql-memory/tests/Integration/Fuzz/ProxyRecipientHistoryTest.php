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
final class ProxyRecipientHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE USER a,b',
            'GRANT PROXY ON root TO a,missing,b,other_missing WITH GRANT OPTION',
            'SHOW GRANTS FOR a',
            'SHOW GRANTS FOR b',
            'GRANT PROXY ON root TO a,b WITH GRANT OPTION',
            'REVOKE PROXY ON root FROM a,missing,b,other_missing',
            'SHOW GRANTS FOR a',
            'SHOW GRANTS FOR b',
            'REVOKE PROXY ON root FROM a,b',
            'SHOW GRANTS FOR a',
            'SHOW GRANTS FOR b',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, str_starts_with($release, '5.') ? $statements : [...$statements,
                'REVOKE IF EXISTS PROXY ON root FROM a,missing,b,other_missing',
                'REVOKE IF EXISTS PROXY ON root FROM a,missing,b,other_missing IGNORE UNKNOWN USER',
                'REVOKE PROXY ON root FROM a,missing,b,other_missing IGNORE UNKNOWN USER',
            ]];
        }
    }

    /**
     * @param list<string> $statements Per-recipient conditions and grant state after failed statements
     * The initial root proxy grants match the differential target's explicit fixture.
     */
    #[DataProvider('providerReleases')]
    public function testEveryRecipientIsObservedWithVersionedAtomicity(string $release, array $statements): void
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
