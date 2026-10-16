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
final class ExplainConnectionDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'EXPLAIN ANALYZE FOR CONNECTION 0', 'EXPLAIN ANALYZE FOR CONNECTION 1', 'EXPLAIN ANALYZE FOR CONNECTION 4294967295', 'EXPLAIN ANALYZE FORMAT=TREE FOR CONNECTION 0', 'EXPLAIN ANALYZE FORMAT=JSON FOR CONNECTION 0', 'EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0', 'EXPLAIN ANALYZE FORMAT=XML FOR CONNECTION 0', 'EXPLAIN ANALYZE FOR CONNECTION 18446744073709551616', 'EXPLAIN ANALYZE FOR CONNECTION 1 trailing', 'EXPLAIN FORMAT=JSON INTO @a FOR CONNECTION 0', 'EXPLAIN ANALYZE FORMAT=JSON INTO @a FOR CONNECTION 0', 'EXPLAIN ANALYZE FORMAT=TRADITIONAL INTO @a FOR CONNECTION 0', 'EXPLAIN INTO @a FOR CONNECTION 0', 'EXPLAIN FORMAT=TRADITIONAL INTO @a FOR CONNECTION 0', 'EXPLAIN FORMAT=XML INTO @a FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @a FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FORMAT=JSON INTO @a FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FORMAT=XML FOR CONNECTION 0', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FOR CONNECTION 18446744073709551616', 'CREATE PROCEDURE p() EXPLAIN ANALYZE FOR CONNECTION 0 trailing', 'EXPLAIN FOR CONNECTION 0', 'EXPLAIN FORMAT=TREE FOR CONNECTION 0', 'EXPLAIN FORMAT=JSON FOR CONNECTION 0'];
        foreach (['5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Connection option refusals and competing format, numeric, syntax and program errors
     */
    #[DataProvider('providerStatements')]
    public function testConnectionOptionsFailAtTheNativeBoundary(string $release, array $statements): void
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
