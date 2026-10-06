<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule::class)]
#[Medium]
final class PrivilegeRuleTest extends TestCase
{
    public function testPrivilegesLowersAllAsAnEmptyList(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT ALL PRIVILEGES ON t TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->privileges($tree->find('privileges')[0]);
        self::assertSame([], $result);
    }

    public function testPrivilegesLowersAllWithColumns(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT ALL (a, b) ON t TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->privileges($tree->find('privileges')[0]);
        self::assertCount(1, $result);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All, $result[0]->name);
        self::assertCount(2, $result[0]->columns);
    }

    public function testListLowersThePrivilegesInOrder(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT insert, SELECT ON t TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->list($tree->find('privilege_list')[0]);
        self::assertSame(['insert', 'select'], [$result[0]->privilege(), $result[1]->privilege()]);
    }

    public function testPrivilegeLowersAKeyword(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT ALTER SYSTEM ON PARAMETER p TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->privilege($tree->find('privilege')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::AlterSystem, $result->name);
    }

    public function testPrivilegeLowersAnIdentifierWithColumns(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT update (a) ON t TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->privilege($tree->find('privilege')[0]);
        self::assertSame('update', $result->privilege());
        self::assertSame('a', $result->columns[0]->value);
    }

    public function testGranteesDropsTheNoiseWordGroup(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO GROUP a, PUBLIC');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->grantees($tree->find('grantee_list')[0]);
        self::assertSame('a', $result[0]->name?->value);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Everyone, $result[1]->kind);
    }

    public function testGrantOptionWhenWritten(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO a WITH GRANT OPTION');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->grantOption($tree->find('opt_grant_grant_option')[0]);
        self::assertTrue($result);
    }

    public function testGrantOptionWhenAbsent(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->grantOption($tree->find('opt_grant_grant_option')[0]);
        self::assertFalse($result);
    }

    public function testGrantorWhenWritten(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO a GRANTED BY CURRENT_USER');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->grantor($tree->find('opt_granted_by')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::CurrentUser, $result?->kind);
    }

    public function testGrantorWhenAbsent(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\PrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->grantor($tree->find('opt_granted_by')[0]);
        self::assertNull($result);
    }
}
