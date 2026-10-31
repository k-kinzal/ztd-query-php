<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\GrantRule;

#[CoversClass(GrantRule::class)]
#[Medium]
final class GrantRuleTest extends TestCase
{
    public function testRolesRetainsEveryAccountWhenTheOrdinaryGrantDomainIsContradicted(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("GRANT a, '' TO b, CURRENT_USER WITH ADMIN OPTION");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles::class, $statement);
        self::assertNotNull($statement->problem);
        self::assertCount(2, $statement->roles);
        self::assertCount(2, $statement->users);
        self::assertTrue($statement->withAdminOption);
        self::assertSame($statement->users, $statement->problem->users);
    }

    public function testRequestKeepsTheSameStatementInsidePrograms(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT ON d.t TO u AS CURRENT_USER');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $create = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges::class, $create->body);
    }

    public function testRequestKeepsAnImmediateNonGlobalAsClause(): void
    {
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        $statement = $stored->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges::class, $statement);
        self::assertSame('ER_UNSUPPORTED_USE_OF_GRANT_AS', $statement->condition()?->error);
        self::assertSame('GRANT ALL ON d.* TO u AS CURRENT_USER', $statement->toString());
    }

    public function testGrantLowersEveryForm(): void
    {
        self::assertSame('GRANT PROXY ON a TO b', (new Semantics(Dialect::MySql))->analyze('grant proxy on a to b')->toString());
        self::assertSame('GRANT r TO u', (new Semantics(Dialect::MySql))->analyze('grant r to u')->toString());
    }

    public function testAllLowersAllPrivileges(): void
    {
        self::assertSame('GRANT ALL PRIVILEGES ON FUNCTION f TO u WITH GRANT OPTION', (new Semantics(Dialect::MySql))->analyze('grant all privileges on function f to u with grant option')->toString());
    }

    public function testRevokeLowersEveryForm(): void
    {
        self::assertSame('REVOKE PROXY ON a FROM b', (new Semantics(Dialect::MySql))->analyze('revoke proxy on a from b')->toString());
        self::assertSame('REVOKE SELECT ON TABLE t FROM u', (new Semantics(Dialect::MySql))->analyze('revoke select on table t from u')->toString());
    }

    public function testRevokePrivilegesRefusesRoutineColumnsBeforePublishingAStatement(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT(a) ON PROCEDURE p FROM u');
    }

    public function testRevokeAllLowersAllPrivileges(): void
    {
        self::assertSame('REVOKE ALL ON db.* FROM u', (new Semantics(Dialect::MySql))->analyze('revoke all on db.* from u')->toString());
    }

    public function testEverythingLowersAllAndGrantOption(): void
    {
        self::assertSame('REVOKE ALL, GRANT OPTION FROM u', (new Semantics(Dialect::MySql))->analyze('revoke all, grant option from u')->toString());
    }

    public function testSpecificationsLowersTheAccounts(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO a, CURRENT_USER', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to a, current_user')->toString());
    }

    public function testAdminLowersTheOption(): void
    {
        self::assertSame('GRANT r TO u WITH ADMIN OPTION', (new Semantics(Dialect::MySql))->analyze('grant r to u with admin option')->toString());
    }

    public function testIgnoreLowersTheOption(): void
    {
        self::assertSame('REVOKE r FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke r from u ignore unknown user')->toString());
    }

    public function testGrantAsLowersTheClause(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u AS a', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to u as a')->toString());
    }
}
