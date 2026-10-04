<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class)]
#[Small]
final class RoleOptionTest extends TestCase
{
    public function testOptionIsOfferedByEveryRoleOption(): void
    {
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleValidity::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleSystemId::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers::class));
    }
}
