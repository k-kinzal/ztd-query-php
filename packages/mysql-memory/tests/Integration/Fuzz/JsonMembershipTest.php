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
final class JsonMembershipTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $inputs = [
            "SELECT CAST('1' AS JSON) IN (1)", "SELECT CAST('1' AS JSON) IN (1,2)",
            "SELECT CAST('1' AS JSON) IN (NULL,1)", "SELECT 1 IN (CAST('1' AS JSON))",
            "SELECT 1 IN (CAST('1' AS JSON),2)", "SELECT 1 IN (2,CAST('1' AS JSON))",
            "SELECT CAST('1' AS JSON) IN (CAST('1' AS JSON),CAST('2' AS JSON))",
            'SELECT JSON_OBJECTAGG(NULL,NULL) IN (NULL,TRUE)', 'SELECT JSON_ARRAYAGG(NULL) IN (NULL,TRUE)',
            'SELECT JSON_OBJECTAGG(1,1) IN (0,2)',
            'SELECT a IN (1,2) FROM t', 'SELECT a IN (1) FROM t', 'SELECT 1 IN (a,2) FROM t',
            'SELECT a IN (1,2) FROM absent', "SELECT CAST('1' AS JSON) IN (1,2) FROM absent",
            "SELECT CAST('1' AS JSON) IN (missing,2)",
            "SELECT CAST('1' AS JSON) IN (1,2), CAST('1' AS JSON) IN (1,2)",
            "SELECT CAST('1' AS JSON) IN (1,2) WHERE FALSE",
            "SELECT CAST('1' AS JSON) IN (SELECT 1)",
            "SELECT (CAST('1' AS JSON),1) IN ((1,1),(2,2))",
            "SELECT (CAST('1' AS JSON),1) IN ((1,1))",
            "SELECT (CAST('1' AS JSON),CAST('2' AS JSON)) IN ((1,2),(2,2))",
            "SELECT (1,1) IN ((CAST('1' AS JSON),1),(CAST('2' AS JSON),2))",
            "SELECT (CAST('[1]' AS JSON),1) IN ((0,1),(2,2))",
            "SELECT CAST('[1]' AS JSON) IN (0,2)",
            "SELECT CAST('\"x\"' AS JSON) IN ('x','y')",
            "SELECT (CAST('\"x\"' AS JSON),1) IN (('x',1),('y',2))",
        ];
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, ['CREATE DATABASE json_domain', 'USE json_domain', 'CREATE TABLE t(a JSON)', ...$inputs, "INSERT INTO t VALUES ('1'), ('2'), (NULL)", ...$inputs]];
        }
    }

    /**
     * @param list<string> $statements JSON comparisons before and after populating their table
     */
    #[DataProvider('providerStatements')]
    public function testJsonMembershipOutcomesMatchNative(string $release, array $statements): void
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
