<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\GrantRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GrantRule::class)]
#[Medium]
final class GrantRuleTest extends TestCase
{
    public function testGrantLowersEveryForm(): void
    {
        self::assertSame('GRANT PROXY ON a TO b', (new Inspection())->inspect('grant proxy on a to b')->toString());
        self::assertSame('GRANT r TO u', (new Inspection())->inspect('grant r to u')->toString());
    }

    public function testAllLowersAllPrivileges(): void
    {
        self::assertSame('GRANT ALL ON FUNCTION f TO u WITH GRANT OPTION', (new Inspection())->inspect('grant all privileges on function f to u with grant option')->toString());
    }

    public function testRevokeLowersEveryForm(): void
    {
        self::assertSame('REVOKE PROXY ON a FROM b', (new Inspection())->inspect('revoke proxy on a from b')->toString());
        self::assertSame('REVOKE SELECT ON t FROM u', (new Inspection())->inspect('revoke select on table t from u')->toString());
    }

    public function testRevokeAllLowersAllPrivileges(): void
    {
        self::assertSame('REVOKE ALL ON db.* FROM u', (new Inspection())->inspect('revoke all on db.* from u')->toString());
    }

    public function testEverythingLowersAllAndGrantOption(): void
    {
        self::assertSame('REVOKE ALL, GRANT OPTION FROM u', (new Inspection())->inspect('revoke all, grant option from u')->toString());
    }

    public function testSpecificationsLowersTheAccounts(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO a, CURRENT_USER', (new Inspection())->inspect('grant select on *.* to a, current_user')->toString());
    }

    public function testAdminLowersTheOption(): void
    {
        self::assertSame('GRANT r TO u WITH ADMIN OPTION', (new Inspection())->inspect('grant r to u with admin option')->toString());
    }

    public function testIgnoreLowersTheOption(): void
    {
        self::assertSame('REVOKE r FROM u IGNORE UNKNOWN USER', (new Inspection())->inspect('revoke r from u ignore unknown user')->toString());
    }

    public function testGrantAsLowersTheClause(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u AS a', (new Inspection())->inspect('grant select on *.* to u as a')->toString());
    }
}
