<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokePrivilegesStatement::class)]
#[Medium]
final class RevokePrivilegesStatementTest extends TestCase
{
    public function testWithPrivilegesReplacesThePrivilegesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('REVOKE SELECT ON t FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->target);
        $changed = $statement->withPrivileges([new ColumnPrivilege(StaticPrivilege::Update, ['a']), StaticPrivilege::Trigger]);
        self::assertSame([StaticPrivilege::Select], $statement->privileges);
        self::assertSame("REVOKE UPDATE(`a`), TRIGGER ON TABLE `t` FROM 'u'", $changed->toString());
    }

    public function testWithTargetReplacesTheLevel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE EVENT ON *.* FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertSame("REVOKE EVENT ON `app`.* FROM 'u'", $statement->withTarget(new DatabaseScope('app'))->toString());
        self::assertSame(PrivilegeScope::Global, $statement->target);
    }

    public function testWithGranteesReplacesTheAccounts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE EVENT ON *.* FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertSame('REVOKE EVENT ON *.* FROM CURRENT_USER', $statement->withGrantees([CurrentAccount::Authenticated])->toString());
        self::assertEquals([new AccountName('u')], $statement->grantees);
    }

    public function testWithIfExistsAddsTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE EVENT ON *.* FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertSame("REVOKE IF EXISTS EVENT ON *.* FROM 'u'", $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithIfExistsRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('REVOKE EVENT ON *.* FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIfExists(true);
    }

    public function testWithIgnoreUnknownUserAddsTheAccountPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE EVENT ON *.* FROM u');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertSame("REVOKE EVENT ON *.* FROM 'u' IGNORE UNKNOWN USER", $statement->withIgnoreUnknownUser(true)->toString());
        self::assertFalse($statement->ignoreUnknownUser);
    }

    public function testWithOriginRetainsTheRevocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE IF EXISTS EXECUTE ON FUNCTION f FROM u IGNORE UNKNOWN USER');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
