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
final class DerivedAliasInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = [
            'SELECT * FROM (SELECT 1)', 'SELECT * FROM missing, (SELECT 1)', 'SELECT * FROM (SELECT 1), missing', 'SELECT * FROM (SELECT * FROM missing)',
            'SELECT * FROM (SELECT 1) WHERE', 'SELECT * FROM (SELECT 1) WHERE BINARY 1', 'SELECT BINARY 1 FROM (SELECT 1)', 'SELECT * FROM (SELECT 1), (SELECT BINARY 2)',
            'SELECT * FROM ((SELECT 1)) d', 'SELECT * FROM ((SELECT 1))', 'SELECT * FROM ((SELECT 1), t) d', 'SELECT * FROM t, t, (SELECT 1)', 'SELECT * FROM (SELECT 1), t, t',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN SELECT * FROM (SELECT 1); END IF; END', 'CREATE PROCEDURE q() BEGIN IF 0 THEN SELECT * FROM (SELECT 1); END IF; SELECT BINARY 2; END',
            'SELECT * FROM (SELECT 1) AS ``', 'SELECT * FROM (((SELECT 1))) d', 'SELECT * FROM (SELECT 1) = d', 'SELECT * FROM ((SELECT 1) x)',
        ];
        $statements = ['SET NAMES utf8mb4', ...$queries, 'CREATE DATABASE d', 'USE d', ...$queries];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_DERIVED_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
            }
            yield $release => [$release, $definition, $statements];
        }
    }

    /**
     * @param list<string> $statements Required aliases, enclosing parentheses and competing input conditions
     */
    #[DataProvider('providerInputs')]
    public function testAliasRefusalsPreserveNativeInputOrder(string $release, DisposableMySql $definition, array $statements): void
    {
        $container = Testcontainers::run($definition);
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
