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
final class TemporalReadingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testTemporalInterpretationFollowsGrammarConditions(string $release): void
    {
        $statements = [
            "SELECT TIMESTAMP 'text', CAST(1 AT LOCAL AS CHAR)",
            "SELECT CAST(1 AT LOCAL AS CHAR), TIMESTAMP 'text'",
            "SELECT CAST(TIMESTAMP 'text' AT LOCAL AS CHAR)",
            "SELECT CAST(1 AT LOCAL AS DATETIME), TIMESTAMP 'text'",
            "SELECT !1, TIMESTAMP 'text', CAST(1 AT LOCAL AS CHAR)",
            "SELECT TIMESTAMP 'text', BINARY 1, CAST(1 AT LOCAL AS CHAR)",
            "SELECT CAST(1 AT LOCAL AS CHAR), DATE 'text', CAST(2 AT LOCAL AS CHAR)",
            "SELECT DATE 'text', 1 COLLATE missing",
            "SELECT 1 COLLATE missing, DATE 'text'",
            "SELECT DATE 'text', CAST(1 AS DATETIME(7))",
            "SELECT CAST(1 AS DATETIME(7)), DATE 'text'",
            "SELECT !1, DATE 'text', BINARY 1",
            "SELECT DATE 'text', @a:=1",
            "SELECT DATE 'text' FROM missing",
            "SELECT (SELECT DATE 'bad1'), DATE 'bad2'",
        ];
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
