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
final class BinaryOperandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "SELECT 'a' COLLATE nope, BINARY CAST(1 AT LOCAL AS SIGNED), BINARY CAST(2 AT LOCAL AS SIGNED)",
            'SELECT CAST(1 AT LOCAL AS SIGNED), BINARY CAST(2 AT LOCAL AS SIGNED)',
            'SELECT BINARY CAST(1 AT LOCAL AS SIGNED), BINARY 2',
            'SELECT CAST(1 AT LOCAL AS DATETIME), BINARY 2',
            'SELECT CAST(1 AT LOCAL AS DATETIME),?',
            'SELECT CONCAT(BINARY CAST(1 AT LOCAL AS SIGNED), BINARY CAST(2 AT LOCAL AS SIGNED))',
            'SELECT BINARY CAST(1 AT LOCAL AS SIGNED)+BINARY CAST(2 AT LOCAL AS SIGNED)',
            "SELECT 'a' COLLATE nope, BINARY 2, BINARY 3",
            "SELECT 'a' COLLATE nope + BINARY CAST(1 AT LOCAL AS SIGNED), BINARY 3",
            'SHOW STATUS WHERE BINARY CAST(1 AT LOCAL AS SIGNED) + BINARY CAST(2 AT LOCAL AS SIGNED)',
            'SELECT (SELECT BINARY CAST(1 AT LOCAL AS SIGNED)) + BINARY CAST(2 AT LOCAL AS SIGNED)',
            'SELECT CAST(BINARY CAST(1 AT LOCAL AS SIGNED) AS SIGNED) + BINARY CAST(2 AT LOCAL AS SIGNED)',
            'SELECT (BINARY CAST(1 AT LOCAL AS SIGNED)) UNION SELECT BINARY CAST(2 AT LOCAL AS SIGNED)',
            'SELECT BINARY (CAST(1 AT LOCAL AS SIGNED)), BINARY 2',
            'SELECT BINARY (1+CAST(1 AT LOCAL AS SIGNED)), BINARY 2',
            'SELECT BINARY (CAST(1 AT LOCAL AS SIGNED)+1), BINARY 2',
            "SELECT BINARY ('a' COLLATE nope), BINARY 2",
            'SELECT BINARY BINARY CAST(1 AT LOCAL AS SIGNED), BINARY 2',
            'SELECT BINARY CAST(1 AT LOCAL AS SIGNED),?',
            'SELECT BINARY (CAST(1 AT LOCAL AS SIGNED)),?',
            'SELECT (CAST(1 AT LOCAL AS SIGNED)), BINARY 2',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The operand completion boundaries to compare
     */
    #[DataProvider('providerReleases')]
    public function testBinaryStopsAfterItsRefusedOperand(string $release, array $statements): void
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
