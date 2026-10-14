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
final class GranteeHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'GRANT PROXY ON CURRENT_USER TO CURRENT_USER WITH GRANT OPTION',
            'CREATE USER app',
            'GRANT SELECT ON *.* TO app',
            'SHOW GRANTS FOR app',
            'GRANT INSERT, UPDATE ON *.* TO app',
            'SHOW GRANTS FOR app',
            'CREATE USER proxied',
            'GRANT PROXY ON proxied TO app',
            'SHOW GRANTS FOR app',
            'GRANT USAGE ON *.* TO app WITH GRANT OPTION',
            'SHOW GRANTS FOR app',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, str_starts_with($release, '5.') ? [...$statements,
                "GRANT USAGE ON *.* TO legacy IDENTIFIED BY 'pw'",
                'SHOW GRANTS FOR legacy',
                "GRANT PROXY ON proxied TO legacy IDENTIFIED BY 'next'",
                'SHOW GRANTS FOR legacy',
            ] : $statements];
        }
    }

    /**
     * @param list<string> $statements The account history to compare
     */
    #[DataProvider('providerReleases')]
    public function testRetainedGranteesPreserveNativeAccountHistories(string $release, array $statements): void
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
