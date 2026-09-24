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
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDefaultRolesStatement::class)]
#[Medium]
final class AlterDefaultRolesStatementTest extends TestCase
{
    public function testWithAccountReplacesTheAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE r');
        self::assertInstanceOf(AlterDefaultRolesStatement::class, $statement);
        $changed = $statement->withAccount(CurrentAccount::Authenticated);
        self::assertEquals(new AccountName('a'), $statement->account);
        self::assertSame("ALTER USER CURRENT_USER DEFAULT ROLE 'r'", $changed->toString());
    }

    public function testWithRolesReplacesTheDefaultRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE r');
        self::assertInstanceOf(AlterDefaultRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName('x', 'h'), new AccountName('y')]);
        self::assertCount(1, $statement->roles);
        self::assertSame("ALTER USER 'a' DEFAULT ROLE 'x' @'h', 'y'", $changed->toString());
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE r, s');
        self::assertInstanceOf(AlterDefaultRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
    }

    public function testWithOriginRejectsAMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE r');
        self::assertInstanceOf(AlterDefaultRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
