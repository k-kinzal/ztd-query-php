<?php

declare(strict_types=1);

namespace Tests\Unit;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Instance::class)]
#[Small]
final class InstanceTest extends TestCase
{
    public function testConnectResolvesQualifiedTablesWithoutACurrentDatabase(): void
    {
        $instance = new Instance();
        $writer = $instance->connect();
        $writer->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $reader = $instance->connect();
        $result = $reader->query('SELECT * FROM d.t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testShutdownEndsEverySessionAndRefusesNewConnections(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $instance->shutdown();

        self::assertTrue($first->released);
        self::assertTrue($second->released);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(2006);

        $instance->connect();
    }

    public function testRestartPreservesDurableRowsAndRollsBackOpenTransactions(): void
    {
        $instance = new Instance(globals: ['max_connections' => 100]);
        $session = $instance->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE m (a INT) ENGINE=MEMORY');
        $session->query('INSERT INTO t VALUES (1); INSERT INTO m VALUES (2); SET GLOBAL max_connections = 200');
        $session->query('BEGIN; INSERT INTO t VALUES (3)');
        $instance->restart();
        $again = $instance->connect(database: 'd');
        $rows = $again->query('SELECT a FROM t; SELECT a FROM m; SELECT @@global.max_connections');

        self::assertTrue($session->released);
        self::assertInstanceOf(ResultSet::class, $rows[0]);
        self::assertInstanceOf(ResultSet::class, $rows[1]);
        self::assertInstanceOf(ResultSet::class, $rows[2]);
        self::assertSame([[['1']], [], [['100']]], [$rows[0]->rows, $rows[1]->rows, $rows[2]->rows]);
    }

    public function testRestartRefusesAnInstanceWithoutASupervisor(): void
    {
        $instance = new Instance(supervised: false);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3707);
        $this->expectExceptionMessage('Restart server failed (mysqld is not managed by supervisor process).');

        $instance->restart();
    }

    public function testConnectionsCountsTheSessionsOpened(): void
    {
        $instance = new Instance();
        $instance->connect();
        $instance->connect();

        self::assertSame(2, $instance->connections());
    }

    public function testConnectNumbersTheConnectionsFromOne(): void
    {
        $instance = new Instance();

        self::assertSame(1, $instance->connect()->id);
        self::assertSame(2, $instance->connect()->id);
    }

    public function testConnectSeesTheAccountsOfANewServer(): void
    {
        $session = (new Instance())->connect();
        $grants = $session->query("SHOW GRANTS FOR 'mysql.sys'@localhost")[0];

        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame(['root@localhost', 'root@%', 'mysql.infoschema@localhost', 'mysql.session@localhost', 'mysql.sys@localhost'], array_values(array_map(static fn ($account): string => $account->identity->text(), $session->instance->accounts->accounts)));
        self::assertSame([['GRANT USAGE ON *.* TO `mysql.sys`@`localhost`'], ['GRANT AUDIT_ABORT_EXEMPT,FIREWALL_EXEMPT,SYSTEM_USER ON *.* TO `mysql.sys`@`localhost`'], ['GRANT TRIGGER ON `sys`.* TO `mysql.sys`@`localhost`'], ['GRANT SELECT ON `sys`.`sys_config` TO `mysql.sys`@`localhost`']], $grants->rows);
    }

    public function testConnectOpensASessionAsTheUserFromTheHost(): void
    {
        $session = (new Instance())->connect('app', '10.0.0.2');

        self::assertSame('app', $session->user);
        self::assertSame('10.0.0.2', $session->host);
        self::assertSame('', $session->variables->database);
        $result = $session->query('SELECT USER(), CURRENT_USER()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['app@10.0.0.2', 'app@%']], $result->rows);
    }

    public function testConnectUsesADatabaseCreatedAtStart(): void
    {
        $session = (new Instance('8.4.7', [], ['shop']))->connect('root', 'localhost', 'shop');

        $result = $session->query('SELECT DATABASE()')[0];

        self::assertSame('shop', $session->variables->database);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['shop']], $result->rows);
    }

    public function testConnectRefusesAnUnknownDatabase(): void
    {
        $instance = new Instance();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'shop'");

        $instance->connect('root', 'localhost', 'shop');
    }

    public function testConnectSharesTheDatabasesOfTheServer(): void
    {
        $instance = new Instance();
        $instance->connect()->query('CREATE DATABASE shop');
        $result = $instance->connect('root', 'localhost', 'shop')->query('SELECT DATABASE()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['shop']], $result->rows);
    }

    public function testConnectStartsWithTheSystemDatabases(): void
    {
        $instance = new Instance();

        self::assertSame(['information_schema', 'mysql', 'performance_schema', 'sys'], array_keys($instance->dictionary->schemas));
    }

    public function testConnectReadsGlobalValuesByLowerCaseName(): void
    {
        $instance = new Instance('8.4.7', ['SQL_MODE' => 'ANSI_QUOTES']);

        $result = $instance->connect()->query('SELECT @@sql_mode')[0];

        self::assertSame(['sql_mode' => 'ANSI_QUOTES'], $instance->globals->values);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ANSI_QUOTES']], $result->rows);
    }

    public function testConnectEmulatesTheRelease(): void
    {
        $instance = new Instance('8.0.44');

        $result = $instance->connect()->query('SELECT VERSION()')[0];

        self::assertSame('8.0.44', $instance->version);
        self::assertNull($instance->clientHost);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['8.0.44']], $result->rows);
    }

    public function testConnectStartsTheConnectionsOf57InLatin1(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        self::assertSame(['latin1', 'latin1_swedish_ci'], [$session->variables->read('character_set_client'), $session->variables->read('collation_connection')]);
        self::assertSame('utf8mb4', (new Instance())->connect()->variables->read('character_set_client'));
    }

    public function testConnectsFindTheSystemDatabasesOfTheRelease(): void
    {
        self::assertSame([['information_schema', 'utf8mb3_general_ci'], ['mysql', 'latin1_swedish_ci'], ['performance_schema', 'utf8mb3_general_ci']], array_map(static fn ($schema): array => [$schema->name, $schema->collation], array_values((new Instance('5.6.51'))->dictionary->schemas)));
        self::assertSame(['information_schema', 'mysql', 'performance_schema', 'sys'], array_keys((new Instance())->dictionary->schemas));
    }

    public function testConnectRecordsTheSession(): void
    {
        $instance = new Instance();
        $s = $instance->connect();

        self::assertSame($s, $instance->sessions[$s->id]->get());
        self::assertLessThanOrEqual(microtime(true), $instance->started);
    }
}
