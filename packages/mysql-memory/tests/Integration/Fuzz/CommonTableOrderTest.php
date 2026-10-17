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
final class CommonTableOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "WITH c AS (SELECT BINARY '1'), c AS (SELECT BINARY '2') SELECT BINARY '3'",
            "WITH c AS (SELECT 1), c AS (SELECT 1) SELECT BINARY '3'",
            "WITH c AS (SELECT BINARY '1'), c AS (SELECT 1) SELECT 1",
            "WITH x AS (WITH c AS (SELECT 1), c AS (SELECT 1) SELECT BINARY '1') SELECT BINARY '2'",
            "SELECT (WITH c AS (SELECT BINARY '1'), c AS (SELECT BINARY '2') SELECT 1), BINARY '3'",
            'WITH c AS (SELECT !1), c AS (SELECT !2) SELECT !3',
            'WITH c AS (SELECT @a := 1), c AS (SELECT @a := 2) SELECT @a := 3',
            "WITH c AS (SELECT !1), C AS (SELECT BINARY '2') SELECT !3",
            'WITH c AS (SELECT 1 AS a), C AS (SELECT 2 AS b) SELECT c.a, C.b FROM c, C',
            'SELECT a.x, A.y FROM (SELECT 1 AS x) a, (SELECT 2 AS y) A',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Common table definitions with ordered warnings
     */
    #[DataProvider('providerReleases')]
    public function testCommonTableErrorsAndWarningsMatchMySql(string $release, array $statements): void
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
