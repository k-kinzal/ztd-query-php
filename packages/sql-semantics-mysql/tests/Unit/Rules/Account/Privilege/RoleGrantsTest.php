<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\Privilege\RoleGrants;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoleGrants::class)]
#[Small]
final class RoleGrantsTest extends TestCase
{
    public function testContradictoryKeepsSessionIdentitySeparateFromFixedAccountContradictions(): void
    {
        $roles = [new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('r'), new Name(' Host ')))];
        $rule = new RoleGrants();
        self::assertTrue($rule->contradictory($roles, [new M\Name\AccountName(new Name('r'), new Name('host'))]));
        self::assertTrue($rule->contradictory($roles, [new M\Name\AccountName(new Name(''))]));
        self::assertFalse($rule->contradictory($roles, [new M\Name\AccountName(new Name('R'), new Name('host'))]));
        self::assertFalse($rule->contradictory($roles, [new M\Name\CurrentUser()]));
        self::assertTrue($rule->contradictory([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]));
    }
}
