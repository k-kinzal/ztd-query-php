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
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantProxyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantProxyStatement::class)]
#[Medium]
final class GrantProxyStatementTest extends TestCase
{
    public function testWithProxiedReplacesTheProxiedAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT PROXY ON p TO u');
        self::assertInstanceOf(GrantProxyStatement::class, $statement);
        $changed = $statement->withProxied(CurrentAccount::Authenticated);
        self::assertEquals(new AccountName('p'), $statement->proxied);
        self::assertSame("GRANT PROXY ON CURRENT_USER TO 'u'", $changed->toString());
    }

    public function testWithGranteesReplacesTheRecipients(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT PROXY ON p TO u');
        self::assertInstanceOf(GrantProxyStatement::class, $statement);
        self::assertSame("GRANT PROXY ON 'p' TO 'v' @'h', 'w'", $statement->withGrantees([new AccountName('v', 'h'), new AccountName('w')])->toString());
        self::assertCount(1, $statement->grantees);
    }

    public function testWithGranteesRejectsACredentialOnMySql8(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT PROXY ON p TO u');
        self::assertInstanceOf(GrantProxyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withGrantees([new AccountDefinition(new AccountName('v'), new PluginIdentification('x'))]);
    }

    public function testWithWithGrantOptionReplacesTheOnwardGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('GRANT PROXY ON p TO u');
        self::assertInstanceOf(GrantProxyStatement::class, $statement);
        self::assertSame("GRANT PROXY ON 'p' TO 'u' WITH GRANT OPTION", $statement->withWithGrantOption(true)->toString());
        self::assertFalse($statement->withGrantOption);
    }

    public function testWithOriginRetainsTheProxyGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("GRANT PROXY ON p TO u IDENTIFIED WITH q AS 'z' WITH GRANT OPTION");
        self::assertInstanceOf(GrantProxyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
