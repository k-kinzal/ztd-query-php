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
final class DebugOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $user = str_repeat('u', 33);
        $host = str_repeat('h', 256);
        $statements = [
            "SHOW PARSE_TREE DROP USER IF EXISTS '{$user}'",
            "SHOW PARSE_TREE CREATE USER '{$user}'",
            "SHOW PARSE_TREE ALTER USER '{$user}' ACCOUNT LOCK",
            "SHOW PARSE_TREE DROP USER 'u'@'{$host}'",
            "SHOW PARSE_TREE DROP USER 'u'@'a@b'",
            "SHOW PARSE_TREE GRANT ALL ON *.* TO '{$user}'",
            'SHOW PARSE_TREE SELECT absent_column',
            "SHOW /* marker */ PARSE_TREE DROP USER 'u'",
        ];
        foreach (['8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The debug-only statements to compare
     */
    #[DataProvider('providerReleases')]
    public function testDebugRequestChecksParsedAccountOperands(string $release, array $statements): void
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
