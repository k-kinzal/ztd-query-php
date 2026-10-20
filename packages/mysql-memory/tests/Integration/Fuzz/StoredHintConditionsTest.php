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
final class StoredHintConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $group = $release === '5.7.44' ? '' : ' RESOURCE_GROUP(a) RESOURCE_GROUP(b)';
            $hint = '/*+ NO_ICP(t@missing i) MAX_EXECUTION_TIME(2)' . $group . ' */';
            $bodies = ['SELECT ' . $hint . ' 1', 'BEGIN SELECT ' . $hint . ' 1; SELECT ' . $hint . ' 2; END', 'IF FALSE THEN UPDATE ' . $hint . ' absent SET a=1; END IF', 'BEGIN SELECT ' . $hint . ' 1; SET @a=2; END', 'SELECT ' . $hint . ' 1 FROM absent', 'BEGIN SELECT ' . $hint . ' 1; UPDATE absent SET a=ROW(1,2); END', 'BEGIN SELECT ' . $hint . ' 1; SELECT ROW(1,2); END'];
            $queries = ['CREATE DATABASE d', 'USE d'];
            foreach ($bodies as $index => $body) {
                array_push($queries, 'CREATE PROCEDURE p' . $index . '() ' . $body, 'CALL p' . $index . '()', 'CALL p' . $index . '()');
            }
            array_push($queries, 'FLUSH TABLES', 'CALL p0()', "ALTER PROCEDURE p0 COMMENT 'reload'", 'CALL p0()', 'CALL p0()');
            if ($release !== '5.7.44') {
                array_push($queries, 'SELECT (SELECT /*+ RESOURCE_GROUP(a) */ 1)', 'SELECT /*+ RESOURCE_GROUP(missing) */ (SELECT /*+ RESOURCE_GROUP(a) */ 1)');
            }
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $queries Creation, cold and warm calls, failed lookups and invalidation
     */
    #[DataProvider('providerHistories')]
    public function testStoredHintConditionsMatchThroughoutTheDefinitionLifetime(string $release, array $queries): void
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
