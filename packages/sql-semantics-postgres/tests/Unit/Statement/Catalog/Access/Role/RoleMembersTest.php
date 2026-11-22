<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers::class)]
#[Medium]
final class RoleMembersTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('adminmembers', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::Admin, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'))]))->option());
    }

    public function testRenderWritesTheKeywordsAndTheRoles(): void
    {
        self::assertSame('CREATE ROLE r IN GROUP a, b ROLE c USER d ADMIN CURRENT_USER IN ROLE e', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE ROLE r IN GROUP a, b ROLE c USER d ADMIN CURRENT_USER IN ROLE e')->toString());
    }

    public function testRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('A role list names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::Role, []);
    }
}
