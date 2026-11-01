<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;

#[CoversClass(RefusedGrantRoles::class)]
#[Small]
final class RefusedGrantRolesTest extends TestCase
{
    public function testMessageDescribesTheFixedAccountDomain(): void
    {
        $roles = [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('')))];
        $users = [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()];
        self::assertSame('GRANT cannot use anonymous accounts or grant an explicit account to itself.', (new RefusedGrantRoles($roles, $users))->message());
    }
}
