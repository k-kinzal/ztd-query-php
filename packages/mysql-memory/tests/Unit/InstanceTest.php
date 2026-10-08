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
}
