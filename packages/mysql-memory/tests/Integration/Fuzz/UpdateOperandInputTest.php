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
final class UpdateOperandInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $writes = ['UPDATE t SET a=ROW(1,2)', 'UPDATE t SET missing=ROW(1,2)', 'UPDATE t SET a=ROW(b,2)', 'UPDATE t SET a=ROW(missing,2)', 'UPDATE t SET a=ROW(1,2)+1', 'UPDATE t SET a=ROW(1,2),missing=3', 'UPDATE t SET a=ROW(1,2),b=missing', 'UPDATE t SET a=(SELECT 1,2)', 'UPDATE t,t SET a=ROW(1,2)', 'UPDATE t,u SET t.a=ROW(1,2)', 'UPDATE (SELECT a FROM t) q SET a=ROW(1,2)', 'UPDATE t,u SET missing=ROW(1,2) WHERE absent', 'UPDATE t JOIN u ON absent SET missing=ROW(1,2)', 'UPDATE t SET missing=3 WHERE ROW(1,2)', 'UPDATE t,u SET missing=3 WHERE ROW(1,2)', 'UPDATE t,u SET t.a=ROW(1,2) ORDER BY missing', 'UPDATE t SET a=ROW(1,2) ORDER BY missing', 'UPDATE t SET a=3 ORDER BY ROW(1,2)', 'UPDATE t SET missing=3 ORDER BY ROW(1,2)', 'UPDATE t,u SET t.a=ROW(1,2) LIMIT 1'];
        $queries = [...$writes, 'CREATE DATABASE d', 'USE d', ...$writes, 'CREATE TABLE t(a INT,b INT)', 'CREATE TABLE u(a INT,b INT)', ...$writes, 'INSERT INTO t VALUES(1,2)', 'INSERT INTO u VALUES(3,4)', ...$writes, 'SELECT * FROM t', "PREPARE s FROM 'UPDATE t SET a=ROW(1,2)'", 'EXECUTE s', 'CREATE PROCEDURE p() IF @active THEN UPDATE t SET a=ROW(1,2); END IF', 'SET @active=0', 'CALL p()', 'SET @active=1', 'CALL p()', 'DROP TABLE t', 'CALL p()'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $queries Missing schemas, missing and populated tables, prepared statements and stored calls
     */
    #[DataProvider('providerHistories')]
    public function testRetainedUpdateOperandsMatchTheNativePreparationHistory(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
                $trials = array_map(static fn (string $sql): array => [serialize((new Native())->observe($native, $sql)), serialize((new Native())->observe($memory, $sql))], $queries);
                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                $memory->close();
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
