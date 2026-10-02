<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Roles;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

#[CoversClass(Roles::class)]
#[Small]
final class RolesTest extends TestCase
{
    public function testRoleLowersANameAndTheDesignations(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP ROLE "Admin", PUBLIC, CURRENT_USER');
        $roles = $tree->find('RoleSpec');
        self::assertSame('Admin', $lowering->roles->role($roles[0])->name?->value);
        self::assertSame(RoleSpecKind::Everyone, $lowering->roles->role($roles[1])->kind);
        self::assertSame(RoleSpecKind::CurrentUser, $lowering->roles->role($roles[2])->kind);
    }

    public function testRoleRejectsTheReservedWordNone(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP ROLE none');
        $this->expectException(AnalysisException::class);
        $lowering->roles->role($tree->find('RoleSpec')[0]);
    }

    public function testNameLowersARoleName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE ROLE admin');
        self::assertSame('admin', $lowering->roles->name($tree->find('RoleId')[0])->value);
    }

    public function testNameRejectsADesignation(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE ROLE CURRENT_USER');
        $this->expectException(AnalysisException::class);
        $lowering->roles->name($tree->find('RoleId')[0]);
    }

    public function testRolesLowersARoleList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP ROLE a, SESSION_USER');
        self::assertCount(2, $lowering->roles->roles($tree->find('role_list')[0]));
    }
}
