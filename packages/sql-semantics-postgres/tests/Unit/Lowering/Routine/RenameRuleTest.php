<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\RenameRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

#[CoversClass(RenameRule::class)]
#[Small]
final class RenameRuleTest extends TestCase
{
    public function testRenameLowersRolesAndAttributes(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER GROUP g RENAME TO h; ALTER TYPE t RENAME ATTRIBUTE a TO b RESTRICT');
        $rule = new RenameRule($lowering);
        $role = $rule->rename($tree->find('RenameStmt')[0]);
        $attribute = $rule->rename($tree->find('RenameStmt')[1]);
        self::assertSame([ObjectKind::Role, RoleWord::Group, RenamedPart::Attribute, DropBehavior::Restrict], [$role->kind, $role->roleWord, $attribute->member?->part, $attribute->behavior]);
    }

    public function testMemberLowersAColumnWithoutTheKeyword(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER VIEW v RENAME a TO b');
        self::assertSame(RenamedPart::Column, (new RenameRule($lowering))->member($lowering->productions->form($tree->find('RenameStmt')[0]), 4)?->part);
    }
}
