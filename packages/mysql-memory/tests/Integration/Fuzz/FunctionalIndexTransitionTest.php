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
final class FunctionalIndexTransitionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'CREATE DATABASE placement',
            'USE placement',
            'CREATE TABLE t(a INT,b VARCHAR(10),KEY k((a+1)))',
            'INSERT INTO t VALUES(2,\'x\')',
            'ALTER TABLE t RENAME COLUMN a TO c',
            'SHOW CREATE TABLE t',
            'SELECT * FROM t',
            'ALTER TABLE t CHANGE a c INT',
            'ALTER TABLE t DROP COLUMN a',
            'CREATE TABLE u LIKE t',
            'SHOW CREATE TABLE u',
            'INSERT INTO u VALUES(3,\'y\')',
            'SELECT * FROM u',
            'ALTER TABLE t ADD KEY mix (b,(a+3))',
            'SELECT CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',
            'ALTER TABLE t ADD UNIQUE KEY uk (b,(a+4))',
            'SELECT CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',
            'SELECT * FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',
            'SELECT * FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION LIMIT 0',
            'SELECT * FROM information_schema.key_column_usage WHERE FALSE ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',
            'SHOW INDEX FROM t',
            'ALTER TABLE t ADD KEY k ((missing+1))',
            'ALTER TABLE t ADD KEY k ((RAND()))',
            'ALTER TABLE t DROP KEY k',
            'SHOW CREATE TABLE t',
            'DROP TABLE t,u',
            'CREATE TABLE t(a INT,KEY ((a+1))) AS SELECT 1 AS a',
            'SHOW CREATE TABLE t',
            'SELECT * FROM t',
            'DROP TABLE t',
            'CREATE TEMPORARY TABLE t(a INT, KEY ((a+1)))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT, KEY ((CAST(a AS JSON))))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT, KEY ((JSON_ARRAY(a))))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT, FULLTEXT KEY ((CONCAT(a))))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT, SPATIAL KEY ((POINT(a,a))))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT AUTO_INCREMENT PRIMARY KEY, KEY ((a+1)))',
            'DROP TABLE IF EXISTS t',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Functional index dependencies, inferred types and dictionary metadata
     */
    #[DataProvider('providerStatements')]
    public function testExpressionIndexDependenciesAndInferredTypesMatchMySql(string $release, array $statements): void
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
