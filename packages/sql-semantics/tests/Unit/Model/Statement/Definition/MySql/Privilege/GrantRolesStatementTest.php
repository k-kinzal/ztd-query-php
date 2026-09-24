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
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantRolesStatement::class)]
#[Medium]
final class GrantRolesStatementTest extends TestCase
{
    public function testWithRolesReplacesTheRolesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r TO u');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertSame("GRANT 's'@'h' TO 'u'", $statement->withRoles([new AccountName('s', 'h')])->toString());
        self::assertSame('r', $statement->roles[0]->username);
    }

    public function testWithGranteesReplacesTheRecipients(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r TO u');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertSame("GRANT 'r' TO CURRENT_USER", $statement->withGrantees([CurrentAccount::Authenticated])->toString());
        self::assertCount(1, $statement->grantees);
    }

    public function testWithWithAdminOptionReplacesTheAdministration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r TO u');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertSame("GRANT 'r' TO 'u' WITH ADMIN OPTION", $statement->withWithAdminOption(true)->toString());
        self::assertFalse($statement->withAdminOption);
    }

    public function testWithOriginRetainsTheRoleGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r, s TO u WITH ADMIN OPTION');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
    }

    public function testWithOriginRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r TO u');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithAdminOptionDefaultsToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r TO u');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertFalse((new GrantRolesStatement($statement->origin, $statement->roles, $statement->grantees))->withAdminOption);
    }
}
