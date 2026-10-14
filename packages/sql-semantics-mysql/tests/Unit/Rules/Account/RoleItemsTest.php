<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\RoleItems;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoleItems::class)]
#[Small]
final class RoleItemsTest extends TestCase
{
    public function testCheckedRetainsTheOperandsInOrder(): void
    {
        $items = [new GrantedRole(new AccountName(new Name('reader'))), new StaticPrivilege(PrivilegeKind::Select), new DynamicPrivilege(new Name('p'), [new Name('c')])];
        self::assertSame($items, (new RoleItems())->checked($items, 'Missing role list.'));
    }

    public function testCheckedRejectsAllPrivileges(): void
    {
        $this->expectExceptionMessage('A role list holds roles; a name without columns reads as a role.');
        (new RoleItems())->checked([new AllPrivileges()], 'Missing role list.');
    }

    public function testCheckedRejectsAnUnqualifiedDynamicPrivilege(): void
    {
        $this->expectExceptionMessage('A role list holds roles; a name without columns reads as a role.');
        (new RoleItems())->checked([new DynamicPrivilege(new Name('reader'))], 'Missing role list.');
    }

    public function testCheckedPreservesTheEnclosingStatementDiagnostic(): void
    {
        $this->expectExceptionMessage('Missing role list.');
        (new RoleItems())->checked([], 'Missing role list.');
    }
}
