<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\Roles;

#[CoversClass(Roles::class)]
#[Medium]
final class RolesTest extends TestCase
{
    public function testWriteDoesNotChangeRoleSelectionIntoSystemVariableAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET ROLE NONE');
        self::assertSame('SET ROLE NONE', $statement->toString());
        self::assertSame($statement::class, $binder->bind($statement->toString())::class);
    }

    public function testAccountsQuoteNamesWithSqlPunctuationAsSingleNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET ROLE 'reader'");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName("x'; DROP TABLE t; --", 'local@host')]);
        $again = $binder->bind($changed->toString());
        self::assertInstanceOf(SetExplicitRolesStatement::class, $again);
        self::assertCount(1, $again->roles);
        self::assertSame("x'; DROP TABLE t; --", $again->roles[0]->username);
        self::assertSame('local@host', $again->roles[0]->host);
    }
}
