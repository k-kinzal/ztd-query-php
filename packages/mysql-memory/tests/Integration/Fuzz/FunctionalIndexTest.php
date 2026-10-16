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
final class FunctionalIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'CREATE DATABASE placement',
            'USE placement',
            'CREATE TABLE t(a INT NOT NULL,b VARCHAR(10), UNIQUE KEY k ((a+1)))',
            'SHOW CREATE TABLE t',
            'SHOW INDEX FROM t',
            'SHOW COLUMNS FROM t',
            'SELECT * FROM information_schema.columns WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY ORDINAL_POSITION',
            'SELECT * FROM information_schema.columns WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY ORDINAL_POSITION LIMIT 0',
            'SELECT COLUMN_NAME,ORDINAL_POSITION,EXTRA FROM information_schema.columns WHERE FALSE ORDER BY ORDINAL_POSITION',
            'SELECT COLUMN_NAME,ORDINAL_POSITION,EXTRA FROM information_schema.columns WHERE TABLE_SCHEMA=\'placement\' AND TABLE_NAME=\'t\' ORDER BY ORDINAL_POSITION',
            'INSERT INTO t VALUES(1,\'x\')',
            'INSERT INTO t VALUES(1,\'y\')',
            'INSERT INTO t VALUES(2,\'z\')',
            'UPDATE t SET a=1 WHERE a=2',
            'SELECT * FROM t ORDER BY a',
            'ALTER TABLE t ADD KEY ((a*2))',
            'SHOW CREATE TABLE t',
            'ALTER TABLE t ADD UNIQUE KEY ((LOWER(b)))',
            'SHOW CREATE TABLE t',
            'INSERT INTO t VALUES(3,\'X\')',
            'DROP TABLE t',
            'CREATE TABLE t(a INT,KEY ((a)))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT,PRIMARY KEY ((a+1)))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT,KEY ((RAND())))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT,KEY ((1)))',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT,KEY ((a+1))) ENGINE=MEMORY',
            'DROP TABLE IF EXISTS t',
            'CREATE TABLE t(a INT,KEY ((a+1))) ENGINE=MyISAM',
            'DROP TABLE IF EXISTS t',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Functional index histories, metadata and refused declarations
     */
    #[DataProvider('providerStatements')]
    public function testExpressionIndexesPreserveWritesAlterationsAndMetadata(string $release, array $statements): void
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
