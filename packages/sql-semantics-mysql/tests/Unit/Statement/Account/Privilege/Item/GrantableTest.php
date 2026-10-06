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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

#[CoversNothing]
#[Medium]
final class GrantableTest extends TestCase
{
    public function testEveryItemImplementsTheInterface(): void
    {
        $grant = (new Semantics(Dialect::MySql))->analyze('GRANT SELECT, BACKUP_ADMIN, r@h ON *.* TO u');

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertContainsOnlyInstancesOf(Grantable::class, $grant->statement->privileges);
        self::assertInstanceOf(StaticPrivilege::class, $grant->statement->privileges[0]);
        self::assertInstanceOf(DynamicPrivilege::class, $grant->statement->privileges[1]);
        self::assertInstanceOf(GrantedRole::class, $grant->statement->privileges[2]);
    }
}
