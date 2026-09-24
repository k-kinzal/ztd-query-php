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
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeRolesStatement::class)]
#[Medium]
final class RevokeRolesStatementTest extends TestCase
{
    public function testWithRolesReplacesTheRolesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r FROM u');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame("REVOKE 's', 't' @'h' FROM 'u'", $statement->withRoles([new AccountName('s'), new AccountName('t', 'h')])->toString());
        self::assertCount(1, $statement->roles);
    }

    public function testWithGranteesReplacesTheAccounts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r FROM u');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame("REVOKE 'r' FROM CURRENT_USER", $statement->withGrantees([CurrentAccount::Authenticated])->toString());
        self::assertEquals([new AccountName('u')], $statement->grantees);
    }

    public function testWithIfExistsAddsTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r FROM u');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame("REVOKE IF EXISTS 'r' FROM 'u'", $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithIgnoreUnknownUserAddsTheAccountPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r FROM u');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame("REVOKE 'r' FROM 'u' IGNORE UNKNOWN USER", $statement->withIgnoreUnknownUser(true)->toString());
        self::assertFalse($statement->ignoreUnknownUser);
    }

    public function testWithOriginRetainsTheRevocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE IF EXISTS r, s FROM u IGNORE UNKNOWN USER');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
    }

    public function testWithOriginRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r FROM u');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
