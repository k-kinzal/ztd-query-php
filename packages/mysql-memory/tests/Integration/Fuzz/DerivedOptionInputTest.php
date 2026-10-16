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
final class DerivedOptionInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [];
            foreach (['HIGH_PRIORITY', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS', 'ALL DISTINCT HIGH_PRIORITY', 'SQL_BUFFER_RESULT HIGH_PRIORITY', 'SQL_CALC_FOUND_ROWS SQL_BUFFER_RESULT'] as $option) {
                foreach (['SELECT ' . $option . ' 1', 'SELECT 1 UNION SELECT ' . $option . ' 2', '(SELECT ' . $option . ' 1) UNION (SELECT 2)'] as $body) {
                    $query = 'SELECT * FROM (' . $body . ') d';
                    $queries[] = $query;
                    $queries[] = $query . ' WHERE BINARY 1';
                    $queries[] = $query . ' WHERE ?';
                    $queries[] = 'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $query . '; END IF; END';
                    $queries[] = 'DELETE missing FROM (' . $body . ') d';
                }
            }
            if (str_starts_with($release, '5.')) {
                array_push($queries, 'SELECT * FROM (SELECT SQL_CACHE SQL_CACHE HIGH_PRIORITY 1) d', 'SELECT * FROM (SELECT HIGH_PRIORITY SQL_NO_CACHE 1) d', 'SELECT * FROM (SELECT SQL_CACHE HIGH_PRIORITY 1) d', 'SELECT * FROM (SELECT SQL_CACHE 1) d', 'DELETE missing FROM (SELECT SQL_CACHE 1) d');
            }
            yield $release => [$release, [...$queries, 'CREATE DATABASE derived_domain', 'USE derived_domain', ...$queries]];
        }
    }

    /**
     * @param list<string> $statements Derived modifier roles, stored definitions and competing input conditions
     */
    #[DataProvider('providerStatements')]
    public function testDerivedOptionsPreserveNativeInputOrder(string $release, array $statements): void
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
