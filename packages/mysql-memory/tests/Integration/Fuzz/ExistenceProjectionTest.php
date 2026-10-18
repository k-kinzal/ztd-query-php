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
final class ExistenceProjectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(v INT, n INT NOT NULL)', 'INSERT INTO t VALUES(NULL,1)'];
        foreach (['SELECT *', 'SELECT * FROM DUAL', 'SELECT * FROM t', 'SELECT t.* FROM t', 'SELECT missing.* FROM t', 'SELECT * FROM absent', 'SELECT *,7', 'SELECT *,7 FROM t'] as $body) {
            foreach (['UNION SELECT 1', 'UNION SELECT 1,2', 'INTERSECT SELECT 1', 'INTERSECT SELECT 2', 'EXCEPT SELECT 1'] as $set) {
                $statements[] = 'SELECT EXISTS(' . $body . ' ' . $set . ') AS answer';
            }
        }
        foreach (['SELECT NULL', 'SELECT 1', 'SELECT 1 FROM t', 'SELECT NULL FROM t', 'SELECT v FROM t', 'SELECT n FROM t', 'SELECT v+1 FROM t', 'SELECT * FROM t', 'SELECT missing.* FROM t', 'SELECT missing', 'SELECT v FROM t UNION SELECT 1', 'SELECT n FROM t UNION SELECT NULL', 'SELECT * UNION SELECT NULL', 'SELECT 1 WHERE FALSE', 'SELECT COUNT(*) FROM t', 'SELECT MAX(v) FROM t', 'SELECT v FROM t WHERE FALSE'] as $body) {
            $statements[] = 'SELECT EXISTS(' . $body . ') AS answer';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Existence projections, set comparisons, unresolved dependencies and result attributes
     */
    #[DataProvider('providerInputs')]
    public function testExistenceProjectionAndMetadataMatchNativeQueries(string $release, array $statements): void
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
