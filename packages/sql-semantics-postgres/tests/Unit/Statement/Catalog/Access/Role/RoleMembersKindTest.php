<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::class)]
#[Small]
final class RoleMembersKindTest extends TestCase
{
    public function testOptionNamesTheOptionTheSpellingFills(): void
    {
        self::assertSame('rolemembers', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::User->option());
        self::assertSame('rolemembers', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::Role->option());
        self::assertSame('adminmembers', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::Admin->option());
        self::assertSame('addroleto', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::InGroup->option());
        self::assertSame('addroleto', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::InRole->option());
    }
}
