<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;

#[CoversClass(Grantor::class)]
#[Medium]
final class GrantorTest extends TestCase
{
    public function testKeepsDefaultRolesWhenNoSelectionIsGiven(): void
    {
        $grantor = new Grantor(CurrentAccount::Authenticated);
        self::assertSame(CurrentAccount::Authenticated, $grantor->account);
        self::assertNull($grantor->roles);
    }

    public function testKeepsAnExplicitRoleSelectionOrPolicy(): void
    {
        $selection = new RoleSelection([new AccountName('r1')]);
        self::assertSame($selection, (new Grantor(new AccountName('g'), $selection))->roles);
        self::assertSame(SessionRolePolicy::None, (new Grantor(new AccountName('g'), SessionRolePolicy::None))->roles);
    }
}
