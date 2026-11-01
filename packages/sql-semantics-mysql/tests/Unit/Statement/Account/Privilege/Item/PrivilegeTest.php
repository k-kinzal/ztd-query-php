<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

#[CoversNothing]
#[Small]
final class PrivilegeTest extends TestCase
{
    public function testThePrivilegeDomainExcludesRoleOperands(): void
    {
        self::assertSame([true, true, true, false], array_map(static fn (string $class): bool => is_a($class, Privilege::class, true), [AllPrivileges::class, StaticPrivilege::class, DynamicPrivilege::class, GrantedRole::class]));
    }
}
