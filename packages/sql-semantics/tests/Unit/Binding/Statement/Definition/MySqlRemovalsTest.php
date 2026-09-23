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
        self::assertSame('DROP DATABASE IF EXISTS `app`', $database->toString());
        $event = $binder->bind('DROP EVENT IF EXISTS app.daily');
        self::assertInstanceOf(Statement\DropEventStatement::class, $event);
        self::assertSame(['app', 'daily'], $event->name->parts);
        self::assertTrue($event->ifExists);
        $server = $binder->bind("DROP SERVER IF EXISTS 'remote'");
        self::assertInstanceOf(Statement\DropServerStatement::class, $server);
        self::assertSame('remote', $server->name);
        self::assertTrue($server->ifExists);
        self::assertSame($database->toString(), $binder->bind($database->toString())->toString());
        self::assertSame($event->toString(), $binder->bind($event->toString())->toString());
        self::assertSame($server->toString(), $binder->bind($server->toString())->toString());
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
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
        self::assertSame($roles->toString(), $binder->bind($roles->toString())->toString());
        self::assertSame($group->toString(), $binder->bind($group->toString())->toString());
    }

}
