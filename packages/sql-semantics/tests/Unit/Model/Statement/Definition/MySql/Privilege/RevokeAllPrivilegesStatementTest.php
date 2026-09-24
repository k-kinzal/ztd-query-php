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
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeAllPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeAllPrivilegesStatement::class)]
#[Medium]
final class RevokeAllPrivilegesStatementTest extends TestCase
{
    public function testWithTargetReplacesTheLevelWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL ON *.* FROM u');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        self::assertSame("REVOKE ALL PRIVILEGES ON * FROM 'u'", $statement->withTarget(PrivilegeScope::CurrentDatabase)->toString());
        self::assertSame(PrivilegeScope::Global, $statement->target);
    }

    public function testWithGranteesReplacesTheAccounts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL ON *.* FROM u');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        self::assertSame("REVOKE ALL PRIVILEGES ON *.* FROM 'v', CURRENT_USER", $statement->withGrantees([new AccountName('v'), CurrentAccount::Authenticated])->toString());
        self::assertCount(1, $statement->grantees);
    }

    public function testWithIfExistsAddsTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL ON *.* FROM u');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        self::assertSame("REVOKE IF EXISTS ALL PRIVILEGES ON *.* FROM 'u'", $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithIgnoreUnknownUserRejectsTheMySql56Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('REVOKE ALL ON *.* FROM u');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIgnoreUnknownUser(true);
    }

    public function testWithIgnoreUnknownUserAddsTheAccountPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL ON *.* FROM u');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        self::assertSame("REVOKE ALL PRIVILEGES ON *.* FROM 'u' IGNORE UNKNOWN USER", $statement->withIgnoreUnknownUser(true)->toString());
        self::assertFalse($statement->ignoreUnknownUser);
    }

    public function testWithOriginRetainsTheRevocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE IF EXISTS ALL ON app.* FROM u IGNORE UNKNOWN USER');
        self::assertInstanceOf(RevokeAllPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
