<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\MySqlRemovals::class)]
#[Medium]
final class MySqlRemovalsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindNamedObjectsAcrossMySqlReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $database = $binder->bind('DROP SCHEMA IF EXISTS app');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $database);
        self::assertSame('app', $database->name);
        self::assertTrue($database->ifExists);
        self::assertSame('DROP DATABASE IF EXISTS `app`', (new \SqlSemantics\SimpleSerializer())->serialize($database));
        $event = $binder->bind('DROP EVENT IF EXISTS app.daily');
        self::assertInstanceOf(Statement\DropEventStatement::class, $event);
        self::assertSame(['app', 'daily'], $event->name->parts);
        self::assertTrue($event->ifExists);
        $server = $binder->bind("DROP SERVER IF EXISTS 'remote'");
        self::assertInstanceOf(Statement\DropServerStatement::class, $server);
        self::assertSame('remote', $server->name);
        self::assertTrue($server->ifExists);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($database), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($database))));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($event), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($event))));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($server), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($server))));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testAccountsPreserveSymbolicAndNamedTargetsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("DROP USER CURRENT_USER(), 'reader'@'localhost', 'CURRENT_USER'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        self::assertSame(CurrentAccount::Authenticated, $statement->accounts[0]);
        self::assertInstanceOf(AccountName::class, $statement->accounts[1]);
        self::assertSame('reader', $statement->accounts[1]->username);
        self::assertSame('localhost', $statement->accounts[1]->host);
        self::assertInstanceOf(AccountName::class, $statement->accounts[2]);
        self::assertSame('CURRENT_USER', $statement->accounts[2]->username);
        self::assertFalse($statement->ifExists);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRolesAndResourceGroupPoliciesAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $roles = $binder->bind("DROP ROLE IF EXISTS 'reader'@'localhost', writer");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $roles);
        self::assertSame('reader', $roles->roles[0]->username);
        self::assertSame('localhost', $roles->roles[0]->host);
        self::assertTrue($roles->ifExists);
        $group = $binder->bind('DROP RESOURCE GROUP workers FORCE');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $group);
        self::assertSame('workers', $group->name);
        self::assertTrue($group->force);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($roles), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($roles))));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($group), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($group))));
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith([Dialect::MySql, 'drop resource group g', Statement\DropResourceGroupStatement::class, 'DROP RESOURCE GROUP `g`'])]
    #[TestWith([Dialect::MySql, 'drop database d', Statement\DropDatabaseStatement::class, 'DROP DATABASE `d`'])]
    #[TestWith([Dialect::MySql, 'drop user u@h, current_user', Statement\DropUsersStatement::class, 'DROP USER \'u\'@\'h\', CURRENT_USER'])]
    #[TestWith([Dialect::MySql, 'drop role r', Statement\DropRolesStatement::class, 'DROP ROLE \'r\''])]
    #[TestWith([Dialect::MySql, 'drop server ``', Statement\DropServerStatement::class, 'DROP SERVER ``'])]
    #[TestWith([Dialect::MySql, 'drop event e', Statement\DropEventStatement::class, 'DROP EVENT `e`'])]
    #[TestWith([Dialect::MySql, 'create role r', Statement\Account\CreateRolesStatement::class, 'CREATE ROLE \'r\''])]
    #[TestWith([Dialect::PostgreSql, 'drop role r', \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement::class, 'DROP ROLE "r"'])]
    public function testBindReadsOnlyMySqlRemovalsInAnyCase(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindRejectsAnEmptyDatabaseName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('A database operation requires a nonempty database name.');
        $binder->bind('drop database ``');
    }

    public function testAccountsReadsParsedAccounts(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("DROP USER 'u'@'h', CURRENT_USER");
        self::assertEquals([new AccountName('u', 'h'), CurrentAccount::Authenticated], \SqlSemantics\Binding\Statement\Definition\MySqlRemovals::accounts($source, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }
}
