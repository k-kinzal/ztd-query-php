<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

#[CoversNothing]
#[Medium]
final class GrantableTest extends TestCase
{
    public function testEveryItemImplementsTheInterface(): void
    {
        $grant = (new Semantics(Dialect::MySql))->analyze('GRANT SELECT, BACKUP_ADMIN ON *.* TO u');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $grant);
        self::assertInstanceOf(GrantPrivileges::class, $grant);
        self::assertContainsOnlyInstancesOf(Grantable::class, $grant->privileges);
        self::assertInstanceOf(StaticPrivilege::class, $grant->privileges[0]);
        self::assertInstanceOf(DynamicPrivilege::class, $grant->privileges[1]);
        $roles = (new Semantics(Dialect::MySql))->analyze('GRANT r@h TO u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles::class, $roles);
        self::assertContainsOnlyInstancesOf(Grantable::class, $roles->roles);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege::class, $grant->privileges);
    }
}
