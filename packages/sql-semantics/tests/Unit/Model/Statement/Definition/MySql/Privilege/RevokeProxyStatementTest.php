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
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeProxyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeProxyStatement::class)]
#[Medium]
final class RevokeProxyStatementTest extends TestCase
{
    public function testWithProxiedReplacesTheProxiedAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE PROXY ON p FROM u');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        self::assertSame("REVOKE PROXY ON 'q' @'h' FROM 'u'", $statement->withProxied(new AccountName('q', 'h'))->toString());
        self::assertEquals(new AccountName('p'), $statement->proxied);
    }

    public function testWithGranteesReplacesTheAccounts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE PROXY ON p FROM u');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        self::assertSame("REVOKE PROXY ON 'p' FROM CURRENT_USER", $statement->withGrantees([CurrentAccount::Authenticated])->toString());
        self::assertCount(1, $statement->grantees);
    }

    public function testWithIfExistsAddsTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE PROXY ON p FROM u');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        self::assertSame("REVOKE IF EXISTS PROXY ON 'p' FROM 'u'", $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithIgnoreUnknownUserAddsTheAccountPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE PROXY ON p FROM u');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        self::assertSame("REVOKE PROXY ON 'p' FROM 'u' IGNORE UNKNOWN USER", $statement->withIgnoreUnknownUser(true)->toString());
        self::assertFalse($statement->ignoreUnknownUser);
    }

    public function testWithOriginRejectsAnExistencePolicyOnMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE IF EXISTS PROXY ON p FROM u');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithOriginRetainsTheRevocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE IF EXISTS PROXY ON p FROM u IGNORE UNKNOWN USER');
        self::assertInstanceOf(RevokeProxyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
