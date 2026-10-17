<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StoredColumnFlagsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testOneDirectStructureBecomesReadableWhenItsTableIsCreated(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $setup = ['CREATE DATABASE d', 'USE d', 'SET NAMES utf8mb4'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $setup);
                array_map($session->run(...), $setup);
                $table = new TableReference(new QualifiedName(new Name('t')), alias: new Name(''));
                $fields = array_map(static fn (string $name): SelectExpression => new SelectExpression(new ColumnUse(new Name($name), new QualifiedName(new Name('')))), ['i', 'u', 'f', 'n', 'd', 'tm', 'dt', 'ts', 'y', 'b', 'bb', 's', 'v', 'j']);
                $select = new Select([], $fields, from: $table);
                $node = new QueryRoot($select);
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $before = ['native' => serialize((new Native())->observe($native, $sql)), 'memory' => serialize((new Memory())->observe($session, $node))];
                $fixture = ['CREATE TABLE t(i INT,u INT UNSIGNED,f FLOAT,n DECIMAL(5,2),d DATE,tm TIME,dt DATETIME,ts TIMESTAMP NULL,y YEAR,b BIT(1),bb BIT(64),s VARCHAR(4),v VARBINARY(4),j BLOB) CHARSET utf8mb4 COLLATE utf8mb4_general_ci', "INSERT INTO t VALUES(1,2,1.25,3.50,'2020-01-02','12:34:56','2020-01-02 12:34:56','2020-01-02 12:34:56',2020,b'1',x'FFFFFFFFFFFFFFFF','a',x'61',x'62')"];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $session->text = 'unrelated invalid input';
                $expected = (new Native())->observe($native, $sql);
                $actual = (new Memory())->observe($session, $node);

                self::assertSame($before['native'], $before['memory']);
                self::assertArrayNotHasKey('error', $actual);
                self::assertSame($expected, $actual);
                self::assertSame('unrelated invalid input', $session->text);
                self::assertSame($select, $node->query);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    #[DataProvider('providerReleases')]
    public function testWireMetadataDistinguishesExpressionsFromStoredFields(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = \MySqlMemory\Server\Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $statements = ['CREATE DATABASE d', 'USE d', 'SET NAMES utf8mb4', 'CREATE TABLE t(i INT,u INT UNSIGNED,f FLOAT,n DECIMAL(5,2),d DATE,tm TIME,dt DATETIME,ts TIMESTAMP NULL,y YEAR,b BIT(64),s VARCHAR(4),v VARBINARY(4),j BLOB) CHARSET utf8mb4 COLLATE utf8mb4_general_ci', "INSERT INTO t VALUES(1,2,1.25,3.50,'2020-01-02','12:34:56','2020-01-02 12:34:56','2020-01-02 12:34:56',2020,x'FFFFFFFFFFFFFFFF','a',x'61',x'62')", 'SELECT * FROM t', 'SELECT * FROM t AS ``', 'SELECT * FROM (SELECT 1 AS i,1.5 AS n,1e0 AS f) AS ``', "SELECT * FROM (SELECT x'01' AS h,b'1' AS b,_binary x'01' AS x) AS q", "SELECT 1,1.5,1e0,NULL,b'1',x'00',CAST(1 AS UNSIGNED),CAST(1 AS DECIMAL(5,2))"];
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

}
