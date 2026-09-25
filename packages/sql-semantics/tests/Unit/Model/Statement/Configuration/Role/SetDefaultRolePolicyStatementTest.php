<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolePolicyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetDefaultRolePolicyStatement::class)]
#[Medium]
final class SetDefaultRolePolicyStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRoleOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET DEFAULT ROLE NONE TO 'u'");
        self::assertInstanceOf(SetDefaultRolePolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame([], $copy->assignments());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET DEFAULT ROLE NONE TO 'u'");
        self::assertInstanceOf(SetDefaultRolePolicyStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new SetDefaultRolePolicyStatement($origin, $statement->policy, $statement->accounts);
    }

    public function testWithPolicyRebindsANewSnapshot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET DEFAULT ROLE NONE TO 'u'");
        self::assertInstanceOf(SetDefaultRolePolicyStatement::class, $statement);
        $changed = $statement->withPolicy(DefaultRolePolicy::All);
        self::assertSame(DefaultRolePolicy::All, $changed->policy);
        self::assertSame("SET DEFAULT ROLE NONE TO 'u'", $statement->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString())));
    }

    public function testWithAccountsRebindsANewSnapshot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET DEFAULT ROLE NONE TO 'u'");
        self::assertInstanceOf(SetDefaultRolePolicyStatement::class, $statement);
        $changed = $statement->withAccounts([new AccountName('other', 'localhost')]);
        self::assertSame('other', $changed->accounts[0]->username);
        self::assertSame('localhost', $changed->accounts[0]->host);
        self::assertSame("SET DEFAULT ROLE NONE TO 'u'", $statement->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString())));
    }

}
