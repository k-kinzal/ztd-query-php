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
final class CommonTablePrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'WITH x AS (SELECT 1), x AS (SELECT 2) SELECT ?',
            'WITH x AS (SELECT 1), X AS (SELECT 2) SELECT ?',
            'WITH x AS (SELECT 1), x AS (SELECT ?) SELECT 1',
            'WITH x AS (SELECT !1), x AS (SELECT BINARY 2) SELECT !?',
            "WITH x AS (SELECT 'a' COLLATE nope), x AS (SELECT 1) SELECT ?",
            'WITH x AS (WITH y AS (SELECT 1), y AS (SELECT 2) SELECT 1) SELECT ?',
            'WITH x AS (SELECT 1), y AS (WITH x AS (SELECT 2) SELECT 1) SELECT ?',
            'WITH x AS (SELECT 1), x AS (SELECT 2) ?',
            'SELECT (WITH x AS (SELECT 1), x AS (SELECT 2) SELECT 1),?',
            'WITH x AS (SELECT 1), x AS (SELECT 2), x AS (SELECT BINARY 3) SELECT ?',
            'WITH x AS (SELECT 1), x AS (SELECT 2) SELECT 1',
            'WITH x AS (SELECT 1), X AS (SELECT 2) SELECT 1',
            'WITH RECURSIVE x AS (SELECT 1), x AS (SELECT 2) SELECT ?',
];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The operand completion boundaries to compare
     */
    #[DataProvider('providerReleases')]
    public function testDuplicatesStopAtTheCompletedDefinition(string $release, array $statements): void
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
