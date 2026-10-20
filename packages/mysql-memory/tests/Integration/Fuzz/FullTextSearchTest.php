<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FullTextSearchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', "SET sql_mode='',collation_connection='utf8mb4_bin'"];
        foreach (['InnoDB', 'MyISAM'] as $engine) {
            array_push($statements, 'DROP TABLE IF EXISTS t', 'CREATE TABLE t(id INT PRIMARY KEY,a TEXT,b TEXT,FULLTEXT k(a,b)) ENGINE=' . $engine, "INSERT INTO t VALUES(1,'apple apple banana','orange'),(2,'apple banana','pear'),(3,'banana cherry','pear pear'),(4,'cherry','grape'),(5,NULL,NULL),(6,'the a is','x')");
            foreach (['', ' WITH QUERY EXPANSION', ' IN BOOLEAN MODE'] as $mode) {
                foreach (['apple', 'banana', 'apple banana', 'banana apple', 'apple apple', 'pear', 'missing', 'the', 'a', '', '+apple -orange', '+apple +banana', 'apple ~banana', '>apple', '<apple', '<apple >banana', 'ap*', '+(apple cherry) -pear', '"apple banana"', '"banana apple"'] as $query) {
                    $statements[] = "SELECT id,MATCH(a,b) AGAINST ('" . $query . "'" . $mode . ') AS score FROM t ORDER BY id';
                }
            }
            array_push($statements, 'SELECT id,MATCH(a,b) AGAINST (NULL) AS score FROM t ORDER BY id', 'SELECT id,MATCH(a,b) AGAINST (12345) AS score FROM t ORDER BY id', "INSERT INTO t VALUES(7,'another about novel','cat kitten')", "SELECT id,MATCH(a,b) AGAINST ('another novel cat kitten') AS score FROM t ORDER BY id", "SELECT id,MATCH(a,b) AGAINST ('another novel cat kitten' IN BOOLEAN MODE) AS score FROM t ORDER BY id");
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Search values, engine-specific relevance and complete metadata
     */
    #[DataProvider('providerReleases')]
    public function testSearchReturnsNativeScoresAndMetadataForPopulatedIndexes(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, 0)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
