<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRole::class)]
#[Medium]
final class AlterRoleTest extends TestCase
{
    public function testRenderDropsTheNoiseWordWith(): void
    {
        self::assertSame('ALTER USER CURRENT_USER nosuperuser USER a', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER USER CURRENT_USER WITH NOSUPERUSER USER a')->toString());
    }

    public function testDeriveStatementReportsPublicAndAReservedName(): void
    {
        self::assertSame(['role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE public LOGIN; ')->facts->diagnostics));
    }

    public function testDeriveStatementReportsAReservedName(): void
    {
        self::assertSame(['role name "pg_monitor" is reserved', 'conflicting or redundant options'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE pg_monitor LOGIN LOGIN')->facts->diagnostics));
    }

    public function testRejectsTheGroupWord(): void
    {
        $this->expectExceptionMessage('ALTER GROUP changes members only.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRole(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Group, new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('r')));
    }

    public function testRejectsAnOptionOnlyCreateRoleHas(): void
    {
        $this->expectExceptionMessage('ALTER ROLE accepts no option that only CREATE ROLE has.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRole(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Role, new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('r')), [new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::Admin, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('a'))])]);
    }
}
