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
final class RenameCurrentUserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SELECT CURRENT_USER()',
            'RENAME USER CURRENT_USER TO a, CURRENT_USER TO b@h',
            'SELECT CURRENT_USER()',
            'SHOW GRANTS FOR a',
            'SHOW GRANTS FOR b@h',
            "SHOW GRANTS FOR u@'%'",
            'RENAME USER CURRENT_USER TO c',
            'SELECT CURRENT_USER()',
            'RENAME USER CURRENT_USER TO d, missing TO e',
            'SELECT CURRENT_USER()',
            'SHOW GRANTS FOR c',
            'SHOW GRANTS FOR d',
            'SHOW GRANTS FOR CURRENT_USER',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The account and connection histories to compare
     */
    #[DataProvider('providerReleases')]
    public function testCurrentUserTracksRenamesAndAccountRollback(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $native->exec("CREATE USER u@'%' IDENTIFIED BY 'pw'");
                $native->exec("GRANT ALL ON *.* TO u@'%' WITH GRANT OPTION");
                $memory->exec("CREATE USER u@'%' IDENTIFIED BY 'pw'");
                $memory->exec("GRANT ALL ON *.* TO u@'%' WITH GRANT OPTION");
                $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, 'u', 'pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $memory = new PDO($server->dsn(), 'u', 'pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $watchNative = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, 'u', 'pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $watchMemory = new PDO($server->dsn(), 'u', 'pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
                self::assertSame($observer->observe($watchNative, 'SELECT CURRENT_USER()', true), $observer->observe($watchMemory, 'SELECT CURRENT_USER()', true));
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
