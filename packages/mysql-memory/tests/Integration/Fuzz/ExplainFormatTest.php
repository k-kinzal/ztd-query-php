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
final class ExplainFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            "EXPLAIN FORMAT='XML' SELECT 1",
            "EXPLAIN FORMAT='XML' FOR CONNECTION 0",
            "CREATE PROCEDURE d.p() EXPLAIN FORMAT='XML' SELECT 1",
            "CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; EXPLAIN FORMAT='XML' SELECT 1; END",
            "SHOW PARSE_TREE EXPLAIN FORMAT='XML' SELECT 1",
            "EXPLAIN FORMAT = 'text' SELECT ?",
            'DESC FORMAT = `YaML` REPLACE DELAYED t SET a=?',
            "EXPLAIN FORMAT = 'text' SELECT BINARY 1,?",
            "EXPLAIN FORMAT = 'text' SELECT * FROM absent_table",
            'EXPLAIN FORMAT = JSON SELECT ?',
            'EXPLAIN FORMAT = TREE SELECT ?',
            'EXPLAIN FORMAT = TRADITIONAL SELECT ?',
            'EXPLAIN FORMAT = ? SELECT 1',
            "EXPLAIN FORMAT = 'te''xt' SELECT ?",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The completed and incomplete formats to compare
     */
    #[DataProvider('providerReleases')]
    public function testFormatRefusalPrecedesLaterStatementInput(string $release, array $statements): void
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
