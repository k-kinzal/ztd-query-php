<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule::class)]
#[Medium]
final class RoleRuleTest extends TestCase
{
    public function testStatementLowersCreateGroup(): void
    {
        self::assertSame('CREATE GROUP g USER a', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE GROUP g WITH USER a')->toString());
    }

    public function testStatementLowersAlterGroup(): void
    {
        self::assertSame('ALTER GROUP g DROP USER a', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER GROUP g DROP USER a')->toString());
    }

    public function testStatementLowersDropRole(): void
    {
        self::assertSame('DROP GROUP IF EXISTS g', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP GROUP IF EXISTS g')->toString());
    }

    public function testStatementLowersAlterRoleSet(): void
    {
        self::assertSame('ALTER USER joe RESET ALL', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER USER joe RESET ALL')->toString());
    }

    public function testOptionsLowersTheListInOrder(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE ROLE r LOGIN SYSID 1 INHERIT');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->options($tree->find('OptRoleList')[0]);
        self::assertCount(3, $result);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit::class, $result[2]);
    }

    public function testOptionsLowersAnAlterList(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER ROLE r');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->options($tree->find('AlterOptRoleList')[0]);
        self::assertSame([], $result);
    }

    public function testOptionLowersAPassword(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER ROLE r ENCRYPTED PASSWORD \'p\'');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->option($tree->find('AlterOptRoleElem')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword::class, $result);
        self::assertSame('p', $result->password?->value);
        self::assertFalse($result->unencrypted);
    }

    public function testOptionLowersARoleList(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE ROLE r IN GROUP a, b');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->option($tree->find('CreateOptRoleElem')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers::class, $result);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::InGroup, $result->kind);
    }

    public function testDatabaseLowersTheName(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER ROLE r IN DATABASE d RESET ALL');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->database($tree->find('opt_in_database')[0]);
        self::assertSame('d', $result?->value);
    }

    public function testDatabaseIsNullWhenAbsent(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER ROLE r RESET ALL');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\RoleRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->database($tree->find('opt_in_database')[0]);
        self::assertNull($result);
    }
}
