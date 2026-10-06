<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\RoleRule;

#[CoversClass(RoleRule::class)]
#[Medium]
final class RoleRuleTest extends TestCase
{
    public function testStatementLowersEveryRoleStatement(): void
    {
        self::assertSame('SET DEFAULT ROLE ALL TO u', (new Semantics(Dialect::MySql))->analyze('set default role all to u')->toString());
        self::assertSame('CREATE ROLE r', (new Semantics(Dialect::MySql))->analyze('create role r')->toString());
    }

    public function testRolesLowersTheRoleList(): void
    {
        self::assertSame('DROP ROLE a, b@h', (new Semantics(Dialect::MySql))->analyze('drop role a, b@h')->toString());
    }

    public function testExceptLowersTheExceptList(): void
    {
        self::assertSame('SET ROLE ALL EXCEPT a, b', (new Semantics(Dialect::MySql))->analyze('set role all except a, b')->toString());
    }

    public function testWithRolesLowersEverySelection(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u AS a WITH ROLE NONE', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to u as a with role none')->toString());
        self::assertSame('GRANT SELECT ON *.* TO u AS a WITH ROLE r', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to u as a with role r')->toString());
    }
}
