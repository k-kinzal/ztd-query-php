<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;

#[CoversClass(RoleSelection::class)]
#[Medium]
final class RoleSelectionTest extends TestCase
{
    public function testKeepsTheRolesInRequestOrder(): void
    {
        $roles = [new AccountName('r2'), new AccountName('r1', 'h')];
        self::assertSame($roles, (new RoleSelection($roles))->roles);
    }
}
