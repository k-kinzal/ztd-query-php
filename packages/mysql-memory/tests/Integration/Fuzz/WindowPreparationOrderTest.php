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
final class WindowPreparationOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = [
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM absent',
            'SELECT NTH_VALUE(v,1) FROM LAST OVER () FROM t',
            'SELECT NTH_VALUE(v,1) FROM LAST OVER () FROM absent',
            'SELECT FIRST_VALUE(v) RESPECT NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(v) RESPECT NULLS OVER () FROM absent',
            'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM absent',
            'SELECT FIRST_VALUE(missing) IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(missing) IGNORE NULLS OVER () FROM absent',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER w FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER w FROM absent',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM absent',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM absent',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM absent',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM absent',
            'SELECT SUM(v) OVER (ROWS BETWEEN 2 FOLLOWING AND 1 FOLLOWING) FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN 2 FOLLOWING AND 1 FOLLOWING) FROM absent',
            'SELECT SUM(v) OVER (ROWS -1 PRECEDING) FROM t',
            'SELECT SUM(v) OVER (ROWS -1 PRECEDING) FROM absent',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER (), missing FROM t',
            'SELECT missing, FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t WHERE missing',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t GROUP BY missing',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t HAVING missing',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t ORDER BY missing',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () OVER () FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER w FROM t WINDOW w AS ()',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER w FROM t WINDOW w AS (ORDER BY missing)',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER w FROM t WINDOW w AS (x)',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER (ORDER BY missing) FROM t',
            'SELECT NTH_VALUE(v,0) FROM LAST IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER ()) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER ()) SELECT * FROM c',
            'SELECT * FROM (SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t) s',
            'SELECT (SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t)',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t UNION SELECT 1',
            'SELECT 1 UNION SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT COUNT(*) FROM (SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t) s',
            'SELECT EXISTS(SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t)',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t WHERE FALSE',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER () FROM t LIMIT 0',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW), missing FROM t',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t WHERE (1,2)',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t WINDOW w AS (ORDER BY 1)',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t WINDOW w AS (x)',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW), FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT * FROM (SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t) s',
            'WITH c AS (SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t) SELECT 1',
            'WITH c AS (SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t) SELECT * FROM c',
            'SELECT SUM(v) OVER (GROUPS CURRENT ROW) FROM t UNION SELECT missing FROM t',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS), missing FROM t',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t WHERE (1,2)',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t WINDOW w AS (ORDER BY 1)',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t WINDOW w AS (x)',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS), FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT * FROM (SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t) s',
            'WITH c AS (SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t) SELECT 1',
            'WITH c AS (SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t) SELECT * FROM c',
            'SELECT SUM(v) OVER (ROWS CURRENT ROW EXCLUDE NO OTHERS) FROM t UNION SELECT missing FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING), missing FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t WHERE (1,2)',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t WINDOW w AS (ORDER BY 1)',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t WINDOW w AS (x)',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING), FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT * FROM (SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t) s',
            'WITH c AS (SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t) SELECT 1',
            'WITH c AS (SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t) SELECT * FROM c',
            'SELECT SUM(v) OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING) FROM t UNION SELECT missing FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW), missing FROM t',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t WHERE (1,2)',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t WINDOW w AS (ORDER BY 1)',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t WINDOW w AS (x)',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW), FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT * FROM (SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t) s',
            'WITH c AS (SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t) SELECT 1',
            'WITH c AS (SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t) SELECT * FROM c',
            'SELECT SUM(v) OVER (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW) FROM t UNION SELECT missing FROM t',
            'SELECT FIRST_VALUE(v) IGNORE NULLS OVER (), (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(v) IGNORE NULLS OVER () FROM t',
            'SELECT FIRST_VALUE(missing) OVER () FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(missing) OVER (), (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(missing) OVER () FROM t',
            'SELECT FIRST_VALUE(v) OVER w FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(v) OVER w, (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(v) OVER w FROM t',
            'SELECT FIRST_VALUE(v) OVER (x) FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(v) OVER (x), (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(v) OVER (x) FROM t',
            'SELECT FIRST_VALUE(v) OVER (ORDER BY missing) FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(v) OVER (ORDER BY missing), (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(v) OVER (ORDER BY missing) FROM t',
            'SELECT SUM(v) OVER () FROM t WHERE (1,2)',
            'SELECT SUM(v) OVER (), (1,2) FROM t',
            'SELECT (1,2), SUM(v) OVER () FROM t',
            'SELECT SUM(missing) OVER () FROM t WHERE (1,2)',
            'SELECT SUM(missing) OVER (), (1,2) FROM t',
            'SELECT (1,2), SUM(missing) OVER () FROM t',
            'SELECT SUM(FIRST_VALUE(v) OVER ()) FROM t WHERE (1,2)',
            'SELECT SUM(FIRST_VALUE(v) OVER ()), (1,2) FROM t',
            'SELECT (1,2), SUM(FIRST_VALUE(v) OVER ()) FROM t',
            'SELECT FIRST_VALUE(FIRST_VALUE(v) OVER ()) OVER () FROM t WHERE (1,2)',
            'SELECT FIRST_VALUE(FIRST_VALUE(v) OVER ()) OVER (), (1,2) FROM t',
            'SELECT (1,2), FIRST_VALUE(FIRST_VALUE(v) OVER ()) OVER () FROM t',
            'SELECT 1 + FIRST_VALUE(v) OVER () FROM t WHERE (1,2)',
            'SELECT 1 + FIRST_VALUE(v) OVER (), (1,2) FROM t',
            'SELECT (1,2), 1 + FIRST_VALUE(v) OVER () FROM t',
            'SELECT ABS(FIRST_VALUE(v) OVER ()) FROM t WHERE (1,2)',
            'SELECT ABS(FIRST_VALUE(v) OVER ()), (1,2) FROM t',
            'SELECT (1,2), ABS(FIRST_VALUE(v) OVER ()) FROM t',
            'SELECT RANK() OVER () FROM t WHERE (1,2)',
            'SELECT RANK() OVER (), (1,2) FROM t',
            'SELECT (1,2), RANK() OVER () FROM t',

        ];
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(v INT)', 'INSERT INTO t VALUES(1),(2),(NULL)'];
        foreach ($queries as $index => $query) {
            array_push(
                $statements,
                $query,
                'PREPARE s FROM ' . "'" . str_replace("'", "''", $query) . "'",
                'DEALLOCATE PREPARE s',
                'CREATE VIEW v' . $index . ' AS ' . $query,
                'CREATE PROCEDURE p' . $index . '() BEGIN IF @run THEN ' . $query . '; END IF; END',
                'SET @run=0',
                'CALL p' . $index . '()',
                'SET @run=1',
                'CALL p' . $index . '()',
                'SET @run=0',
                'CALL p' . $index . '()'
            );
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Windows across direct queries, preparation, views, and skipped or reached stored instructions
     */
    #[DataProvider('providerStatements')]
    public function testWindowChecksRetainTheirQueryBlockAndPreparationOrder(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            $observed = array_map(static function (string $sql) use ($native, $memory): array {
                return [serialize((new Native())->observe($native, $sql)), serialize((new Native())->observe($memory, $sql))];
            }, $statements);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            $native->close();
            $memory->close();
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
