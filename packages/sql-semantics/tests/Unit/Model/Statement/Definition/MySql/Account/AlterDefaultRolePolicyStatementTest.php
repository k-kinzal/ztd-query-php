<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolePolicyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDefaultRolePolicyStatement::class)]
#[Medium]
final class AlterDefaultRolePolicyStatementTest extends TestCase
{
    public function testWithAccountReplacesTheAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE ALL');
        self::assertInstanceOf(AlterDefaultRolePolicyStatement::class, $statement);
        $changed = $statement->withAccount(CurrentAccount::Authenticated);
        self::assertEquals(new AccountName('a'), $statement->account);
        self::assertSame('ALTER USER CURRENT_USER DEFAULT ROLE ALL', $changed->toString());
    }

    public function testWithPolicyReplacesThePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE ALL');
        self::assertInstanceOf(AlterDefaultRolePolicyStatement::class, $statement);
        $changed = $statement->withPolicy(DefaultRolePolicy::None);
        self::assertSame(DefaultRolePolicy::All, $statement->policy);
        self::assertSame("ALTER USER 'a' DEFAULT ROLE NONE", $changed->toString());
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE NONE');
        self::assertInstanceOf(AlterDefaultRolePolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(DefaultRolePolicy::None, $copy->policy);
    }

    public function testWithOriginRejectsAMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE NONE');
        self::assertInstanceOf(AlterDefaultRolePolicyStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
