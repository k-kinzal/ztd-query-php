<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::class)]
#[Small]
final class RoleFlagTest extends TestCase
{
    public function testOptionNamesTheOptionTheWordFills(): void
    {
        self::assertSame('isreplication', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::NoReplication->option());
        self::assertSame('inherit', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::NoInherit->option());
        self::assertSame('superuser', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::Superuser->option());
    }

    public function testEnabledTellsWhetherTheWordTurnsTheAttributeOn(): void
    {
        self::assertTrue(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::BypassRls->enabled());
        self::assertFalse(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::NoBypassRls->enabled());
    }
}
