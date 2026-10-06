<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule::class)]
#[Medium]
final class DefaultPrivilegeRuleTest extends TestCase
{
    public function testStatementLowersDropOwned(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('DROP OWNED BY a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('DropOwnedStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\DropOwned::class, $result);
    }

    public function testStatementLowersReassignOwned(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('REASSIGN OWNED BY a TO b');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('ReassignOwnedStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\ReassignOwned::class, $result);
    }

    public function testStatementLowersDefaultPrivileges(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DEFAULT PRIVILEGES GRANT SELECT ON TABLES TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('AlterDefaultPrivilegesStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges::class, $result);
    }

    public function testScopesLowersTheClausesInOrder(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DEFAULT PRIVILEGES IN SCHEMA s FOR USER u GRANT SELECT ON TABLES TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->scopes($tree->find('DefACLOptionList')[0]);
        self::assertSame(['schemas', 'roles'], [$result[0]->option(), $result[1]->option()]);
    }

    public function testActionLowersARevoke(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR SELECT ON TABLES FROM a CASCADE');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->action($tree->find('DefACLAction')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke::class, $result);
        self::assertTrue($result->grantOptionOnly);
    }

    public function testObjectsLowersTheKind(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DEFAULT PRIVILEGES GRANT usage ON TYPES TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\DefaultPrivilegeRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->objects($tree->find('defacl_privilege_target')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind::Types, $result);
    }
}
