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
final class PreparedHintConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = ['SELECT /*+ RESOURCE_GROUP(missing) */ ?', 'SELECT /*+ RESOURCE_GROUP(missing) */ ? FROM missing','DELETE /*+ RESOURCE_GROUP(missing) */ FROM missing WHERE a=?','UPDATE /*+ RESOURCE_GROUP(missing) */ missing SET a=?','INSERT /*+ RESOURCE_GROUP(missing) */ INTO missing VALUES (?)','SELECT /*+ SET_VAR(sort_buffer_size=1) RESOURCE_GROUP(missing) */ ? FROM missing','SELECT /*+ BKA(unknown) BKA(unknown) RESOURCE_GROUP(missing) */ ? FROM missing','SELECT /*+ RESOURCE_GROUP(missing) */ absent,? FROM t','SELECT /*+ RESOURCE_GROUP(missing) */ ? FROM t','SELECT /*+ SET_VAR(div_precision_increment=10) */ ?/3','WITH q AS (SELECT ?) DELETE /*+ QB_NAME(q) RESOURCE_GROUP(missing) */ FROM missing','WITH q AS (SELECT ? FROM missing) SELECT /*+ RESOURCE_GROUP(missing) */ 1','WITH q AS (SELECT ? FROM missing) SELECT /*+ RESOURCE_GROUP(missing) */ * FROM q'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $queries Requests compared through text and prepared protocols
     */
    #[DataProvider('providerStatements')]
    public function testPreparationPreservesHintConditionsAcrossDatabaseStates(string $release, array $queries): void
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
                $observed = array_map(static function (array $setup) use ($native, $memory, $queries): array {
                    foreach ($setup as $sql) {
                        $native->query($sql);
                        $memory->query($sql);
                    }
                    return array_merge(...array_map(static function (string $sql) use ($native, $memory): array {
                        return array_map(static function (bool $prepared) use ($native, $memory, $sql): array {
                            $text = $prepared ? $sql : str_replace('?', 'NULL', $sql);
                            $parameters = $prepared ? substr_count($sql, '?') : 0;
                            return [serialize((new Native())->observe($native, $text, $parameters)), serialize((new Native())->observe($memory, $text, $parameters))];
                        }, [false, true]);
                    }, $queries));
                }, [[], ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)']]);
                $trials = array_merge(...$observed);

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
